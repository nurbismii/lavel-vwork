<?php

namespace App\Http\Controllers;

use App\Enums\ProgressStatus;
use App\Enums\UserRole;
use App\Models\WorkPeriod;
use App\Services\VisibleTeam;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProgressReportController extends Controller
{
    public function __invoke(Request $request, VisibleTeam $visibleTeam): View
    {
        $request->validate([
            'period' => ['nullable', 'integer', 'exists:work_periods,id'],
            'user' => ['nullable', 'integer', 'exists:users,id'],
            'frequency' => ['nullable', Rule::in(['weekly', 'monthly'])],
            'start_date' => ['nullable', 'date'],
        ]);

        $actor = $request->user();
        $period = $request->filled('period')
            ? WorkPeriod::query()->findOrFail($request->integer('period'))
            : WorkPeriod::query()->orderByRaw("status = 'open' desc")->latest('period_start')->first();
        $visibleUsers = $visibleTeam->users($actor)
            ->with('organizationalUnit')
            ->orderBy('name')
            ->get();
        $memberId = $request->filled('user')
            ? $request->integer('user')
            : ($actor->role === UserRole::Member ? $actor->id : $visibleUsers->first()?->id);
        $member = $memberId ? $visibleUsers->firstWhere('id', $memberId) : null;

        if ($memberId && ! $member) {
            abort(403);
        }

        $frequency = $request->string('frequency')->toString() ?: 'monthly';
        [$startDate, $endDate] = $this->dateRange($request, $period, $frequency);
        $submission = ($period && $member) ? $member->workloadSubmissions()
            ->where('work_period_id', $period->id)
            ->with([
                'activities' => fn ($query) => $query->whereBetween('activity_date', [$startDate, $endDate]),
                'progressItems' => fn ($query) => $query
                    ->whereBetween('report_date', [$startDate, $endDate])
                    ->orderBy('category')
                    ->orderBy('name')
                    ->orderBy('report_date'),
            ])
            ->first() : null;
        $jobs = $this->jobs(
            $submission?->activities ?? collect(),
            $submission?->progressItems ?? collect(),
        );

        return view('reports.progress', [
            'period' => $period,
            'periods' => WorkPeriod::query()->latest('period_start')->get(),
            'visibleUsers' => $visibleUsers,
            'member' => $member,
            'frequency' => $frequency,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'jobs' => $jobs,
            'statuses' => ProgressStatus::cases(),
        ]);
    }

    private function dateRange(Request $request, ?WorkPeriod $period, string $frequency): array
    {
        if (! $period) {
            return [today(), today()];
        }

        $periodStart = $period->period_start->copy()->startOfDay();
        $periodEnd = $periodStart->copy()->endOfMonth();

        if ($frequency === 'monthly') {
            return [$periodStart, $periodEnd];
        }

        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->string('start_date')->toString())->startOfDay()
            : $periodStart->copy();

        if (! $startDate->betweenIncluded($periodStart, $periodEnd)) {
            throw ValidationException::withMessages([
                'start_date' => 'Awal minggu harus berada dalam periode yang dipilih.',
            ]);
        }

        return [$startDate, $startDate->copy()->addDays(6)->min($periodEnd)];
    }

    private function jobs(Collection $activities, Collection $progressItems): Collection
    {
        $activityGroups = $activities->groupBy(fn ($item) => $this->jobKey($item->category, $item->name));
        $progressGroups = $progressItems->groupBy(fn ($item) => $this->jobKey($item->category, $item->name));
        $keys = $activityGroups->keys()->merge($progressGroups->keys())->unique()->sort()->values();

        return $keys->map(function (string $key) use ($activityGroups, $progressGroups) {
            $actuals = $activityGroups->get($key, collect());
            $supplements = $progressGroups->get($key, collect());
            $latest = $supplements->sortByDesc(
                fn ($item) => $item->report_date->format('Y-m-d').' '.$item->created_at->format('H:i:s.u'),
            )->first();
            $reference = $latest ?? $actuals->first();
            $status = $latest?->status ?? ProgressStatus::Completed;
            $byStatus = collect(ProgressStatus::cases())->mapWithKeys(
                fn (ProgressStatus $itemStatus) => [$itemStatus->value => collect()],
            );
            $byStatus->put($status->value, collect([[
                'progress_summary' => $latest?->progress_summary
                    ?? 'Aktivitas aktual pada periode laporan.',
                'actual_summary' => $this->actualSummary($actuals),
                'report_date' => $latest?->report_date ?? $actuals->max('activity_date'),
                'progress_percentage' => $latest?->progress_percentage ?? 100,
                'target_date' => $latest?->target_date,
                'obstacle_note' => $latest?->obstacle_note,
                'action_note' => $latest?->action_note,
            ]]));

            return [
                'category' => $reference->category,
                'name' => $reference->name,
                'by_status' => $byStatus,
            ];
        })
            ->groupBy('category');
    }

    private function actualSummary(Collection $activities): ?string
    {
        if ($activities->isEmpty()) {
            return null;
        }

        $duration = (int) $activities->sum('required_minutes');
        $volumes = $activities->groupBy('unit')->map(
            fn (Collection $items, string $unit) => number_format((float) $items->sum('monthly_volume'), 2, ',', '.').' '.$unit,
        )->values()->implode(', ');

        return $activities->count().' catatan aktivitas · '.number_format($duration / 60, 1, ',', '.').' jam'
            .($volumes !== '' ? ' · '.$volumes : '');
    }

    private function jobKey(string $category, string $name): string
    {
        return mb_strtolower(trim($category))."\0".mb_strtolower(trim($name));
    }
}

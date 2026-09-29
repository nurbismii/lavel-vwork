<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Enums\WorkloadStatus;
use App\Models\FollowUpAction;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Models\UtilizationThreshold;
use App\Models\WorkPeriod;
use App\Services\VisibleTeam;
use App\Services\WorkloadCalculator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, WorkloadCalculator $calculator, VisibleTeam $visibleTeam): View
    {
        $request->validate([
            'period' => ['nullable', 'integer', 'exists:work_periods,id'],
            'unit' => ['nullable', 'integer', 'exists:organizational_units,id'],
            'status' => ['nullable', Rule::enum(WorkloadStatus::class)],
        ]);
        $actor = $request->user();
        $myOpenFollowUps = FollowUpAction::query()
            ->where('owner_id', $actor->id)
            ->whereIn('status', ['open', 'in_progress'])
            ->count();
        $period = $request->filled('period')
            ? WorkPeriod::query()->findOrFail($request->integer('period'))
            : WorkPeriod::query()->orderByRaw("status = 'open' desc")->latest('period_start')->first();
        $periods = WorkPeriod::query()->latest('period_start')->get();
        $visibleUnitIds = $visibleTeam->users($actor)->whereNotNull('organizational_unit_id')
            ->distinct()->pluck('organizational_unit_id');
        $units = OrganizationalUnit::query()->whereIn('id', $visibleUnitIds)->orderBy('name')->get();
        if ($request->filled('unit')) {
            abort_unless($visibleUnitIds->contains($request->integer('unit')), 403);
        }

        if (! $period) {
            return view('welcome', ['period' => null, 'periods' => $periods, 'units' => $units, 'members' => collect(), 'summary' => $this->emptySummary(), 'dueFollowUps' => 0, 'myOpenFollowUps' => $myOpenFollowUps]);
        }

        $threshold = UtilizationThreshold::effectiveFor($period);
        $users = $visibleTeam->users($actor)
            ->when($request->filled('unit'), fn ($query) => $query->where('organizational_unit_id', $request->integer('unit')))
            ->with(['organizationalUnit', 'workloadSubmissions' => fn ($query) => $query
                ->where('work_period_id', $period->id)->with(['capacity', 'activities'])])->get();

        $members = $users->map(function (User $user) use ($calculator, $threshold) {
            $submission = $user->workloadSubmissions->first();
            $metrics = $submission
                ? $calculator->summarize($submission, $threshold)
                : ['effective_minutes' => 0, 'required_minutes' => 0, 'utilization' => null, 'status' => WorkloadStatus::Unavailable, 'by_type' => []];

            return compact('user', 'submission', 'metrics');
        });
        if ($request->filled('status')) {
            $members = $members->filter(fn ($item) => $item['metrics']['status']->value === $request->string('status')->toString());
        }
        $dueFollowUps = FollowUpAction::query()
            ->whereIn('organizational_unit_id', $users->pluck('organizational_unit_id')->filter()->unique())
            ->whereDate('target_date', '<=', today())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();

        $approved = $members->filter(fn ($item) => $item['submission']?->status === SubmissionStatus::Approved);
        $effective = (int) $approved->sum(fn ($item) => $item['metrics']['effective_minutes']);
        $required = (int) $approved->sum(fn ($item) => $item['metrics']['required_minutes']);
        $distribution = $approved->countBy(fn ($item) => $item['metrics']['status']->value);

        $summary = [
            'effective_minutes' => $effective,
            'required_minutes' => $required,
            'utilization' => $effective > 0 ? round(($required / $effective) * 100, 1) : null,
            'approved_count' => $approved->count(),
            'submitted_count' => $members->filter(fn ($item) => $item['submission']?->status === SubmissionStatus::Submitted)->count(),
            'distribution' => $distribution,
            'threshold' => $threshold,
        ];

        return view('welcome', compact('period', 'periods', 'units', 'members', 'summary', 'dueFollowUps', 'myOpenFollowUps'));
    }

    private function emptySummary(): array
    {
        return ['effective_minutes' => 0, 'required_minutes' => 0, 'utilization' => null, 'approved_count' => 0, 'submitted_count' => 0, 'distribution' => collect(), 'threshold' => null];
    }
}

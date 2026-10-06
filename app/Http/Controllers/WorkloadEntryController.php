<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Http\Requests\ActivityRequest;
use App\Models\ActivityMasterOption;
use App\Models\UtilizationThreshold;
use App\Models\WorkloadActivity;
use App\Models\WorkloadSubmission;
use App\Models\WorkPeriod;
use App\Services\ActivityMasterOptionResolver;
use App\Services\AuditLogger;
use App\Services\SubmissionWorkflow;
use App\Services\WorkloadCalculator;
use App\Services\WorkScheduleCalculator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WorkloadEntryController extends Controller
{
    public function show(Request $request, WorkloadCalculator $calculator, WorkScheduleCalculator $scheduleCalculator): View
    {
        $period = WorkPeriod::query()->where('status', 'open')->latest('period_start')->first();
        $submission = $period ? WorkloadSubmission::query()->firstOrCreate([
            'work_period_id' => $period->id, 'user_id' => $request->user()->id,
        ], ['status' => SubmissionStatus::Draft]) : null;

        if ($submission) {
            $this->ensureStandardCapacity($submission, $period, $calculator, $scheduleCalculator);
        }
        $submission?->load(['capacity', 'activities.workTypeOption', 'progressItems', 'validationHistories.actor', 'workPeriod']);
        $metrics = $submission ? $calculator->summarize($submission, UtilizationThreshold::effectiveFor($period)) : null;
        $activityGroups = $submission?->activities
            ->groupBy(fn (WorkloadActivity $activity) => $activity->category."\0".$activity->name)
            ->map(function ($activities) {
                $representative = $activities->sortByDesc('activity_date')->first();

                return [
                    'activity_id' => $representative->id,
                    'category' => $representative->category,
                    'name' => $representative->name,
                    'count' => $activities->count(),
                    'minutes' => (int) $activities->sum('required_minutes'),
                ];
            })
            ->sortBy(fn (array $group) => $group['category']."\0".$group['name'])
            ->values() ?? collect();
        $categoryOptions = ActivityMasterOption::query()->where('type', ActivityMasterOption::TYPE_CATEGORY)->where('is_active', true)->orderBy('label')->get();
        $workTypeOptions = ActivityMasterOption::query()->where('type', ActivityMasterOption::TYPE_WORK_TYPE)->where('is_active', true)->orderBy('label')->get();

        return view('workload.entry', compact('period', 'submission', 'metrics', 'activityGroups', 'categoryOptions', 'workTypeOptions'));
    }

    public function activity(
        ActivityRequest $request,
        AuditLogger $audit,
        ActivityMasterOptionResolver $optionResolver,
        WorkloadCalculator $calculator,
        WorkScheduleCalculator $scheduleCalculator,
    ): RedirectResponse
    {
        $submission = $this->editableSubmission($request);
        $values = $request->validated();
        $activityDate = Carbon::parse($values['activity_date'])->startOfDay();
        $periodStart = $submission->workPeriod->period_start->copy()->startOfDay();
        $periodEnd = $submission->workPeriod->period_start->copy()->endOfMonth()->startOfDay();

        if (! $activityDate->betweenIncluded($periodStart, $periodEnd)) {
            throw ValidationException::withMessages([
                'activity_date' => 'Tanggal aktivitas harus berada dalam periode '.$periodStart->translatedFormat('F Y').'.',
            ]);
        }

        if (($values['duration_unit'] ?? null) === 'day') {
            $this->ensureStandardCapacity($submission, $submission->workPeriod, $calculator, $scheduleCalculator);
        }
        $actualMinutes = isset($values['actual_duration'])
            ? (int) round((float) $values['actual_duration'] * match ($values['duration_unit']) {
                'hour' => 60,
                'day' => (float) $submission->capacity->hours_per_day * 60,
                default => 1,
            })
            : (int) $values['actual_minutes'];

        if ($actualMinutes < 1 || $actualMinutes > 1440) {
            throw ValidationException::withMessages([
                'actual_duration' => 'Durasi setelah konversi harus antara 1 dan 1.440 menit untuk satu tanggal aktivitas.',
            ]);
        }

        try {
            DB::transaction(function () use ($submission, $values, $activityDate, $actualMinutes, $audit, $request, $optionResolver) {
                $categoryOption = $optionResolver->resolve(ActivityMasterOption::TYPE_CATEGORY, $values['category'], $request->user());
                $workTypeOption = $optionResolver->resolve(ActivityMasterOption::TYPE_WORK_TYPE, $values['work_type'], $request->user());

                foreach ([$categoryOption, $workTypeOption] as $option) {
                    if ($option->wasRecentlyCreated) {
                        $audit->record('activity_master_option.created', $option, null, $option->toArray(), $request);
                    }
                }

                $actualVolume = (float) ($values['actual_volume'] ?? 1);
                $activity = $submission->activities()->create([
                    'activity_date' => $activityDate->toDateString(),
                    'name' => $values['name'],
                    'category' => $categoryOption->label,
                    'work_type' => $workTypeOption->value,
                    'monthly_volume' => $actualVolume,
                    'unit' => $values['unit'] ?? 'Aktivitas',
                    'average_minutes_per_unit' => round($actualMinutes / $actualVolume, 2),
                    'required_minutes' => $actualMinutes,
                    'exception_reason' => $values['exception_reason'] ?? null,
                ]);
                $audit->record('activity.created', $activity, null, $activity->toArray(), $request);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'name' => 'Aktivitas yang sama sudah tercatat pada tanggal tersebut. Ubah catatan yang ada atau gunakan nama yang lebih spesifik.',
            ]);
        }

        return back()->with([
            'success' => 'Aktivitas aktual berhasil dicatat.',
            'clear_draft' => 'activity-'.$submission->id,
        ]);
    }

    public function destroyActivity(Request $request, WorkloadActivity $activity, AuditLogger $audit): RedirectResponse
    {
        $activity->load('submission.workPeriod');
        abort_unless($activity->submission->user_id === $request->user()->id && $activity->submission->isEditable(), 403);
        DB::transaction(function () use ($activity, $audit, $request) {
            $before = $activity->toArray();
            $audit->record('activity.deleted', $activity, $before, null, $request);
            $activity->delete();
        });

        return back()->with('success', 'Aktivitas dihapus.');
    }

    public function submit(Request $request, SubmissionWorkflow $workflow, WorkloadCalculator $calculator, WorkScheduleCalculator $scheduleCalculator): RedirectResponse
    {
        $submission = $this->editableSubmission($request);
        $this->ensureStandardCapacity($submission, $submission->workPeriod, $calculator, $scheduleCalculator);
        $request->validate(['member_note' => ['nullable', 'string', 'max:2000']]);
        $workflow->submit($submission, $request->user(), $request->string('member_note')->toString() ?: null);

        return back()->with([
            'success' => 'Data berhasil diajukan kepada atasan.',
            'clear_draft' => 'submission-'.$submission->id,
        ]);
    }

    private function editableSubmission(Request $request): WorkloadSubmission
    {
        $period = WorkPeriod::query()->where('status', 'open')->latest('period_start')->firstOrFail();
        $submission = WorkloadSubmission::query()->firstOrCreate([
            'work_period_id' => $period->id, 'user_id' => $request->user()->id,
        ], ['status' => SubmissionStatus::Draft]);
        $submission->load('workPeriod');
        abort_unless($submission->isEditable(), 403);

        return $submission;
    }

    private function ensureStandardCapacity(
        WorkloadSubmission $submission,
        WorkPeriod $period,
        WorkloadCalculator $calculator,
        WorkScheduleCalculator $scheduleCalculator,
    ): void {
        $member = $submission->user;
        $periodEnd = $period->period_start->copy()->endOfMonth();
        $workDays = $scheduleCalculator->workDays(
            $period->period_start,
            $periodEnd,
            $member->work_cycle_anchor_date,
            (int) $member->cycle_work_days,
            (int) $member->cycle_off_days,
        );
        $calculated = $calculator->capacity(
            $workDays,
            (float) $member->daily_work_hours,
            (float) $period->standard_productive_percentage,
        );

        $submission->capacity()->firstOrCreate([], [
            'work_days' => $workDays,
            'cycle_work_days' => $member->cycle_work_days,
            'cycle_off_days' => $member->cycle_off_days,
            'hours_per_day' => $member->daily_work_hours,
            'work_cycle_anchor_date' => $member->work_cycle_anchor_date,
            'productive_percentage' => $period->standard_productive_percentage,
            ...$calculated,
        ]);
    }
}

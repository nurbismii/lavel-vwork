<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkProgressItemRequest;
use App\Models\ActivityMasterOption;
use App\Models\WorkloadSubmission;
use App\Models\WorkPeriod;
use App\Models\WorkProgressItem;
use App\Services\ActivityMasterOptionResolver;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkProgressController extends Controller
{
    public function store(
        WorkProgressItemRequest $request,
        ActivityMasterOptionResolver $optionResolver,
        AuditLogger $audit,
    ): RedirectResponse {
        $submission = $this->editableSubmission($request);
        $values = $request->validated();
        $entryMode = $values['entry_mode'];
        unset($values['entry_mode']);
        $reportDate = Carbon::parse($values['report_date'])->startOfDay();
        $periodStart = $submission->workPeriod->period_start->copy()->startOfDay();
        $periodEnd = $periodStart->copy()->endOfMonth();

        if (! $reportDate->betweenIncluded($periodStart, $periodEnd)) {
            throw ValidationException::withMessages([
                'report_date' => 'Tanggal laporan harus berada dalam periode '.$periodStart->translatedFormat('F Y').'.',
            ]);
        }

        $sourceActivity = filled($values['source_activity_id'] ?? null)
            ? $submission->activities()->whereKey($values['source_activity_id'])->first()
            : null;

        if (filled($values['source_activity_id'] ?? null) && ! $sourceActivity) {
            throw ValidationException::withMessages([
                'source_activity_id' => 'Aktivitas yang dipilih tidak tersedia pada pengajuan Anda.',
            ]);
        }

        $item = DB::transaction(function () use ($submission, $values, $reportDate, $sourceActivity, $optionResolver, $audit, $request) {
            $category = $sourceActivity?->category;
            $name = $sourceActivity?->name;

            if (! $sourceActivity) {
                $categoryOption = $optionResolver->resolve(
                    ActivityMasterOption::TYPE_CATEGORY,
                    $values['category'],
                    $request->user(),
                );
                $category = $categoryOption->label;
                $name = $values['name'];

                if ($categoryOption->wasRecentlyCreated) {
                    $audit->record('activity_master_option.created', $categoryOption, null, $categoryOption->toArray(), $request);
                }
            }

            $attributes = [
                'report_date' => $reportDate->toDateString(),
                'category' => $category,
                'name' => $name,
            ];
            $payload = [
                ...$values,
                ...$attributes,
                'source_activity_id' => $sourceActivity?->id,
            ];
            $item = $submission->progressItems()->where($attributes)->first();
            $before = $item?->toArray();

            if ($item) {
                $item->update($payload);
                $audit->record('work_progress.updated', $item, $before, $item->fresh()->toArray(), $request);
            } else {
                $item = $submission->progressItems()->create($payload);
                $audit->record('work_progress.created', $item, null, $item->toArray(), $request);
            }

            return $item;
        });

        return back()->with([
            'success' => $item->wasRecentlyCreated
                ? 'Progres pekerjaan berhasil dicatat.'
                : 'Progres pekerjaan pada tanggal tersebut berhasil diperbarui.',
            'clear_draft' => 'progress-'.$entryMode.'-'.$submission->id,
            'progress_item_id' => $item->id,
            'entry_tab' => 'progress',
        ]);
    }

    public function destroy(Request $request, WorkProgressItem $progress, AuditLogger $audit): RedirectResponse
    {
        $progress->load('submission.workPeriod');
        abort_unless(
            $progress->submission->user_id === $request->user()->id
                && $progress->submission->isEditable(),
            403,
        );

        DB::transaction(function () use ($progress, $audit, $request) {
            $before = $progress->toArray();
            $audit->record('work_progress.deleted', $progress, $before, null, $request);
            $progress->delete();
        });

        return back()->with(['success' => 'Catatan progres berhasil dihapus.', 'entry_tab' => 'progress']);
    }

    private function editableSubmission(Request $request): WorkloadSubmission
    {
        $period = WorkPeriod::query()->where('status', 'open')->latest('period_start')->firstOrFail();
        $submission = WorkloadSubmission::query()->firstOrCreate([
            'work_period_id' => $period->id,
            'user_id' => $request->user()->id,
        ]);
        $submission->load('workPeriod');
        abort_unless($submission->isEditable(), 403);

        return $submission;
    }
}

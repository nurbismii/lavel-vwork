<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActivityMasterOptionMergeRequest;
use App\Http\Requests\ActivityMasterOptionUpdateRequest;
use App\Models\ActivityMasterOption;
use App\Models\WorkloadActivity;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ActivityMasterOptionController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::in([ActivityMasterOption::TYPE_CATEGORY, ActivityMasterOption::TYPE_WORK_TYPE])],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'merged'])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $options = ActivityMasterOption::query()
            ->with(['creator', 'mergedInto', 'merger'])
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, function (Builder $query, string $status) {
                match ($status) {
                    'active' => $query->where('is_active', true)->whereNull('merged_into_id'),
                    'inactive' => $query->where('is_active', false)->whereNull('merged_into_id'),
                    'merged' => $query->whereNotNull('merged_into_id'),
                };
            })
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('label', 'like', '%'.$search.'%'))
            ->orderBy('type')->orderBy('label')
            ->paginate(24)->withQueryString();

        $categoryCounts = WorkloadActivity::query()
            ->whereIn('category', $options->getCollection()->where('type', ActivityMasterOption::TYPE_CATEGORY)->pluck('label'))
            ->selectRaw('category, count(*) as aggregate')->groupBy('category')->pluck('aggregate', 'category');
        $workTypeCounts = WorkloadActivity::query()
            ->whereIn('work_type', $options->getCollection()->where('type', ActivityMasterOption::TYPE_WORK_TYPE)->pluck('value'))
            ->selectRaw('work_type, count(*) as aggregate')->groupBy('work_type')->pluck('aggregate', 'work_type');

        $options->getCollection()->each(function (ActivityMasterOption $option) use ($categoryCounts, $workTypeCounts) {
            $option->setAttribute('usage_count', (int) ($option->type === ActivityMasterOption::TYPE_CATEGORY
                ? $categoryCounts->get($option->label, 0)
                : $workTypeCounts->get($option->value, 0)));
        });

        $mergeTargets = ActivityMasterOption::query()
            ->where('is_active', true)->whereNull('merged_into_id')
            ->orderBy('label')->get()->groupBy('type');

        return view('admin.activity-master-options', compact('options', 'mergeTargets', 'filters'));
    }

    public function update(ActivityMasterOptionUpdateRequest $request, ActivityMasterOption $option, AuditLogger $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $option, $audit) {
            $option = ActivityMasterOption::query()->lockForUpdate()->findOrFail($option->id);
            if ($option->merged_into_id) {
                throw ValidationException::withMessages(['label' => 'Opsi yang sudah digabungkan tidak dapat diubah lagi.']);
            }
            $before = $option->toArray();
            $label = str($request->validated('label'))->squish()->toString();
            $affected = 0;

            if ($option->type === ActivityMasterOption::TYPE_CATEGORY && $option->label !== $label) {
                $affected = WorkloadActivity::query()->where('category', $option->label)->update(['category' => $label]);
            }

            $option->update([
                'label' => $label,
                'value' => $option->type === ActivityMasterOption::TYPE_CATEGORY ? $label : $option->value,
                'normalized_key' => $request->validated('normalized_key'),
            ]);
            $audit->record('activity_master_option.renamed', $option, $before, [
                ...$option->fresh()->toArray(), 'affected_activities' => $affected,
            ], $request);
        });

        return back()->with('success', 'Nama opsi master berhasil diperbarui.');
    }

    public function status(Request $request, ActivityMasterOption $option, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);

        DB::transaction(function () use ($request, $option, $audit, $validated) {
            $option = ActivityMasterOption::query()->lockForUpdate()->findOrFail($option->id);
            if ($option->merged_into_id && $validated['is_active']) {
                throw ValidationException::withMessages(['is_active' => 'Opsi hasil merge tidak dapat diaktifkan kembali.']);
            }

            $before = $option->toArray();
            $option->update(['is_active' => $validated['is_active']]);
            $audit->record('activity_master_option.status_changed', $option, $before, $option->fresh()->toArray(), $request);
        });

        return back()->with('success', $validated['is_active'] ? 'Opsi master diaktifkan.' : 'Opsi master dinonaktifkan.');
    }

    public function merge(ActivityMasterOptionMergeRequest $request, ActivityMasterOption $option, AuditLogger $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $option, $audit) {
            $locked = ActivityMasterOption::query()
                ->whereIn('id', [$option->id, $request->integer('target_id')])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $source = $locked->get($option->id);
            $target = $locked->get($request->integer('target_id'));

            if (! $source || ! $target || $source->type !== $target->type || ! $target->is_active || $target->merged_into_id) {
                throw ValidationException::withMessages(['target_id' => 'Tujuan merge tidak valid atau tidak aktif.']);
            }
            if ($source->merged_into_id) {
                throw ValidationException::withMessages(['target_id' => 'Opsi sumber sudah pernah digabungkan.']);
            }

            $before = $source->toArray();
            if ($source->type === ActivityMasterOption::TYPE_WORK_TYPE) {
                $hasConflict = DB::table('workload_activities as source')
                    ->join('workload_activities as target', function ($join) {
                        $join->on('target.workload_submission_id', '=', 'source.workload_submission_id')
                            ->on('target.name', '=', 'source.name');
                    })
                    ->where('source.work_type', $source->value)
                    ->where('target.work_type', $target->value)
                    ->exists();

                if ($hasConflict) {
                    throw ValidationException::withMessages([
                        'target_id' => 'Merge akan menghasilkan aktivitas ganda. Perbaiki aktivitas yang memiliki nama sama terlebih dahulu.',
                    ]);
                }
                $affected = WorkloadActivity::query()->where('work_type', $source->value)->update(['work_type' => $target->value]);
            } else {
                $affected = WorkloadActivity::query()->where('category', $source->label)->update(['category' => $target->label]);
            }

            $source->update([
                'is_active' => false,
                'merged_into_id' => $target->id,
                'merged_by' => $request->user()->id,
                'merged_at' => now(),
            ]);
            $audit->record('activity_master_option.merged', $source, $before, [
                ...$source->fresh()->toArray(), 'target' => $target->only(['id', 'value', 'label']),
                'affected_activities' => $affected,
            ], $request);
        });

        return back()->with('success', 'Opsi duplikat berhasil digabungkan dan pemakaian lama telah dialihkan.');
    }
}

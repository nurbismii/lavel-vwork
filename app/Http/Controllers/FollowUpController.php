<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\FollowUpActionRequest;
use App\Models\FollowUpAction;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Models\WorkloadSubmission;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FollowUpController extends Controller
{
    public function index(Request $request): View
    {
        $actions = $this->scope($request)->with(['owner', 'submission.user', 'submission.workPeriod'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderByRaw('target_date is null')->orderBy('target_date')->get();
        $unitIds = $request->user()->role === UserRole::Administrator
            ? OrganizationalUnit::query()->pluck('id')
            : collect([$request->user()->organizational_unit_id]);

        return view('follow-ups.index', [
            'actions' => $actions,
            'units' => OrganizationalUnit::query()->whereIn('id', $unitIds)->where('is_active', true)->get(),
            'owners' => User::query()->whereIn('organizational_unit_id', $unitIds)->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function ownerSubmissions(Request $request, User $owner): JsonResponse
    {
        abort_unless($owner->is_active, 404);
        $this->assertUnitScope($request, (int) $owner->organizational_unit_id);

        $submissions = WorkloadSubmission::query()
            ->where('user_id', $owner->id)
            ->with('workPeriod:id,period_start')
            ->get()
            ->sortByDesc(fn (WorkloadSubmission $submission) => $submission->workPeriod->period_start)
            ->values()
            ->map(fn (WorkloadSubmission $submission) => [
                'id' => $submission->id,
                'label' => 'Pengajuan Beban Kerja '.$submission->workPeriod->period_start->translatedFormat('F Y').' — '.$submission->status->label(),
            ]);

        return response()->json(['submissions' => $submissions]);
    }

    public function store(FollowUpActionRequest $request, AuditLogger $audit): RedirectResponse
    {
        $values = $request->validated();
        $this->assertUnitScope($request, (int) $values['organizational_unit_id']);
        $this->assertReferencesSameUnit($values);
        $action = DB::transaction(function () use ($values, $audit, $request) {
            $action = FollowUpAction::query()->create($values);
            $audit->record('follow_up.created', $action, null, $action->toArray(), $request);

            return $action;
        });

        return back()->with('success', "Tindak lanjut {$action->title} berhasil dibuat.");
    }

    public function update(FollowUpActionRequest $request, FollowUpAction $action, AuditLogger $audit): RedirectResponse
    {
        abort_unless($this->scope($request)->whereKey($action->id)->exists(), 403);
        $values = $request->validated();
        $this->assertUnitScope($request, (int) $values['organizational_unit_id']);
        $referencesChanged = (int) $action->owner_id !== (int) $values['owner_id']
            || (int) $action->workload_submission_id !== (int) ($values['workload_submission_id'] ?? 0);
        $this->assertReferencesSameUnit($values, $referencesChanged);
        $before = $action->toArray();
        DB::transaction(function () use ($action, $values, $before, $audit, $request) {
            $action->update($values);
            $audit->record('follow_up.updated', $action, $before, $action->fresh()->toArray(), $request);
        });

        return back()->with('success', 'Tindak lanjut berhasil diperbarui.');
    }

    private function scope(Request $request): Builder
    {
        $query = FollowUpAction::query();

        return $request->user()->role === UserRole::Administrator
            ? $query
            : $query->where('organizational_unit_id', $request->user()->organizational_unit_id);
    }

    private function assertUnitScope(Request $request, int $unitId): void
    {
        if ($request->user()->role !== UserRole::Administrator && $request->user()->organizational_unit_id !== $unitId) {
            abort(403);
        }
    }

    private function assertReferencesSameUnit(array $values, bool $requireSubmissionOwner = true): void
    {
        $ownerUnit = User::query()->whereKey($values['owner_id'])->value('organizational_unit_id');
        $submission = filled($values['workload_submission_id'] ?? null)
            ? WorkloadSubmission::query()->whereKey($values['workload_submission_id'])->with('user:id,organizational_unit_id')->first()
            : null;
        $submissionUnit = $submission?->user?->organizational_unit_id ?? $values['organizational_unit_id'];

        if (
            (int) $ownerUnit !== (int) $values['organizational_unit_id']
            || (int) $submissionUnit !== (int) $values['organizational_unit_id']
            || ($requireSubmissionOwner && $submission && $submission->user_id !== (int) $values['owner_id'])
        ) {
            throw ValidationException::withMessages([
                'workload_submission_id' => 'Pengajuan harus milik owner yang dipilih dan berasal dari unit yang sama.',
            ]);
        }
    }
}

<?php

namespace App\Services;

use App\Enums\SubmissionStatus;
use App\Models\User;
use App\Models\WorkloadSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmissionWorkflow
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function submit(WorkloadSubmission $submission, User $actor, ?string $note = null): void
    {
        $submission->loadMissing(['workPeriod', 'capacity', 'activities']);

        if ($submission->user_id !== $actor->id || ! $submission->isEditable()) {
            abort(403);
        }

        if (! $submission->canBeSubmitted()) {
            throw ValidationException::withMessages([
                'submission' => 'Ringkasan baru dapat diajukan setelah periode selesai.',
            ]);
        }

        if (! $submission->capacity || $submission->activities->isEmpty()) {
            throw ValidationException::withMessages([
                'submission' => 'Kapasitas dan minimal satu catatan aktivitas aktual wajib tersedia sebelum diajukan.',
            ]);
        }

        $this->transition($submission, SubmissionStatus::Submitted, $actor, $note, [
            'submitted_at' => now(),
            'member_note' => $note,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_note' => null,
        ]);
    }

    public function review(WorkloadSubmission $submission, SubmissionStatus $target, User $actor, ?string $note): void
    {
        $isValidTransition = match ($submission->status) {
            SubmissionStatus::Submitted => in_array($target, [SubmissionStatus::Approved, SubmissionStatus::RevisionRequired], true),
            SubmissionStatus::Approved => $target === SubmissionStatus::RevisionRequired,
            default => false,
        };

        if (! $isValidTransition) {
            throw ValidationException::withMessages(['status' => 'Transisi status pengajuan tidak valid.']);
        }

        if ($target === SubmissionStatus::RevisionRequired && blank($note)) {
            throw ValidationException::withMessages(['note' => 'Catatan wajib diisi ketika meminta revisi.']);
        }

        $this->transition($submission, $target, $actor, $note, [
            'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_note' => $note,
        ]);
    }

    private function transition(WorkloadSubmission $submission, SubmissionStatus $target, User $actor, ?string $note, array $extra): void
    {
        DB::transaction(function () use ($submission, $target, $actor, $note, $extra) {
            $from = $submission->status;
            $submission->update([...$extra, 'status' => $target]);
            $submission->validationHistories()->create([
                'from_status' => $from->value,
                'to_status' => $target->value,
                'note' => $note,
                'actor_id' => $actor->id,
                'created_at' => now(),
            ]);
            $this->audit->record(
                'submission.status_changed',
                $submission,
                ['status' => $from->value],
                ['status' => $target->value, 'note' => $note],
            );
        });
    }
}

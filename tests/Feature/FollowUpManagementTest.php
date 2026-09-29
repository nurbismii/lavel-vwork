<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Models\WorkloadSubmission;
use App\Models\WorkPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowUpManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_submission_endpoint_only_returns_submissions_for_selected_owner(): void
    {
        [$unit, $manager, $owner, $otherOwner, $period] = $this->context();
        $ownerSubmission = $this->submission($owner, $period, SubmissionStatus::Approved);
        $this->submission($otherOwner, $period, SubmissionStatus::Submitted);

        $this->actingAs($manager)
            ->getJson(route('follow-ups.owner-submissions', $owner))
            ->assertOk()
            ->assertJsonCount(1, 'submissions')
            ->assertJsonPath('submissions.0.id', $ownerSubmission->id)
            ->assertJsonPath('submissions.0.label', 'Pengajuan Beban Kerja Agustus 2026 — Disetujui');

        $this->actingAs($manager)->get(route('follow-ups.index'))
            ->assertOk()
            ->assertSee('data-follow-up-owner', false)
            ->assertSee('data-follow-up-submission', false)
            ->assertDontSee('Pengajuan Beban Kerja Agustus 2026');
    }

    public function test_manager_cannot_load_owner_from_another_unit(): void
    {
        [, $manager] = $this->context();
        $otherUnit = OrganizationalUnit::query()->create([
            'code' => 'FIN', 'name' => 'Finance', 'is_active' => true,
        ]);
        $otherOwner = User::factory()->create([
            'role' => UserRole::Member, 'organizational_unit_id' => $otherUnit->id, 'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->getJson(route('follow-ups.owner-submissions', $otherOwner))
            ->assertForbidden();
    }

    public function test_submission_must_belong_to_selected_owner(): void
    {
        [$unit, $manager, $owner, $otherOwner, $period] = $this->context();
        $otherSubmission = $this->submission($otherOwner, $period, SubmissionStatus::Approved);

        $this->actingAs($manager)->post(route('follow-ups.store'), $this->payload(
            $unit,
            $owner,
            $otherSubmission,
        ))->assertSessionHasErrors('workload_submission_id');

        $this->assertDatabaseCount('follow_up_actions', 0);
    }

    public function test_manager_can_create_follow_up_for_selected_owners_submission(): void
    {
        [$unit, $manager, $owner, , $period] = $this->context();
        $submission = $this->submission($owner, $period, SubmissionStatus::Approved);

        $this->actingAs($manager)->post(route('follow-ups.store'), $this->payload(
            $unit,
            $owner,
            $submission,
        ))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('follow_up_actions', [
            'owner_id' => $owner->id,
            'workload_submission_id' => $submission->id,
            'title' => 'Perbaikan proses pengajuan',
        ]);
    }

    private function payload(OrganizationalUnit $unit, User $owner, WorkloadSubmission $submission): array
    {
        return [
            'organizational_unit_id' => $unit->id,
            'owner_id' => $owner->id,
            'workload_submission_id' => $submission->id,
            'action_type' => 'process_improvement',
            'title' => 'Perbaikan proses pengajuan',
            'description' => 'Tindak lanjut hasil analisis.',
            'target_date' => '2026-09-15',
            'status' => 'open',
        ];
    }

    private function submission(User $owner, WorkPeriod $period, SubmissionStatus $status): WorkloadSubmission
    {
        return WorkloadSubmission::query()->create([
            'work_period_id' => $period->id,
            'user_id' => $owner->id,
            'status' => $status,
        ]);
    }

    private function context(): array
    {
        $unit = OrganizationalUnit::query()->create([
            'code' => 'OPS', 'name' => 'Operasional', 'is_active' => true,
        ]);
        $manager = User::factory()->create([
            'role' => UserRole::Manager, 'organizational_unit_id' => $unit->id, 'is_active' => true,
        ]);
        $owner = User::factory()->create([
            'role' => UserRole::Member, 'organizational_unit_id' => $unit->id, 'is_active' => true,
        ]);
        $otherOwner = User::factory()->create([
            'role' => UserRole::Member, 'organizational_unit_id' => $unit->id, 'is_active' => true,
        ]);
        $period = WorkPeriod::query()->create([
            'period_start' => '2026-08-01',
            'submission_deadline' => '2026-08-26',
            'status' => 'locked',
        ]);

        return [$unit, $manager, $owner, $otherOwner, $period];
    }
}

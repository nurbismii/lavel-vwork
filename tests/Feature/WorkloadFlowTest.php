<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\ActivityMasterOption;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Models\WorkloadSubmission;
use App\Models\WorkPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WorkloadFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_standard_capacity_is_applied_automatically_when_member_opens_entry(): void
    {
        [$member] = $this->context();

        $this->actingAs($member)->get(route('workload.entry'))
            ->assertOk()
            ->assertSee('Kapasitas efektif periode')
            ->assertSee('Pola 6:1')
            ->assertSee('160,7');

        $this->assertDatabaseHas('workload_capacities', [
            'work_days' => 27, 'cycle_work_days' => 6, 'cycle_off_days' => 1,
            'hours_per_day' => 7, 'productive_percentage' => 85,
            'gross_minutes' => 11340, 'effective_minutes' => 9639,
        ]);
    }

    public function test_member_cannot_override_standard_capacity(): void
    {
        [$member] = $this->context();

        $this->actingAs($member)->put('/beban-kerja-saya/kapasitas', [
            'work_days' => 1, 'hours_per_day' => 1, 'productive_percentage' => 1,
        ])->assertNotFound();

        $this->assertDatabaseCount('workload_capacities', 0);
    }

    public function test_capacity_keeps_its_period_snapshot_after_member_schedule_changes(): void
    {
        [$member, , $submission] = $this->context();

        $this->actingAs($member)->get(route('workload.entry'))->assertOk();
        $member->update([
            'cycle_work_days' => 14,
            'cycle_off_days' => 1,
            'daily_work_hours' => 8,
            'work_cycle_anchor_date' => '2026-08-01',
        ]);

        $this->actingAs($member)->get(route('workload.entry'))->assertOk();

        $this->assertDatabaseHas('workload_capacities', [
            'workload_submission_id' => $submission->id,
            'cycle_work_days' => 6,
            'cycle_off_days' => 1,
            'hours_per_day' => 7,
            'work_days' => 27,
        ]);
    }

    public function test_duplicate_actual_activity_on_same_date_is_rejected_with_actionable_message(): void
    {
        [$member, , $submission] = $this->context();
        $payload = [
            'category' => 'Operasional', 'name' => 'Rekonsiliasi', 'work_type' => 'Rutin',
            'activity_date' => '2026-08-10', 'actual_volume' => 10, 'unit' => 'Dokumen',
            'actual_minutes' => 150,
        ];

        $this->actingAs($member)->post(route('workload.activity'), $payload)
            ->assertRedirect()->assertSessionHas('clear_draft', 'activity-'.$submission->id);
        $this->actingAs($member)->post(route('workload.activity'), $payload)->assertSessionHasErrors('name');
        $this->assertSame(1, $submission->activities()->count());
    }

    public function test_member_can_record_same_activity_on_different_dates(): void
    {
        [$member, , $submission] = $this->context();
        $payload = [
            'category' => 'Operasional', 'name' => 'Peninjauan laporan', 'work_type' => 'Rutin',
            'actual_volume' => 3, 'unit' => 'Laporan', 'actual_minutes' => 45,
        ];

        $this->actingAs($member)->post(route('workload.activity'), [
            ...$payload, 'activity_date' => '2026-08-10',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($member)->post(route('workload.activity'), [
            ...$payload, 'activity_date' => '2026-08-11',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(2, $submission->activities()->count());
        $this->assertTrue($submission->activities()
            ->whereDate('activity_date', '2026-08-10')
            ->where('required_minutes', 45)
            ->exists());
    }

    public function test_actual_activity_date_must_be_inside_open_period(): void
    {
        [$member] = $this->context();

        $this->actingAs($member)->post(route('workload.activity'), [
            'activity_date' => '2026-07-31', 'category' => 'Operasional',
            'name' => 'Peninjauan laporan', 'work_type' => 'Rutin', 'actual_minutes' => 45,
        ])->assertSessionHasErrors('activity_date');
    }

    public function test_entry_date_is_server_controlled_and_late_input_is_visible(): void
    {
        [$member] = $this->context();
        $this->travelTo(Carbon::parse('2026-08-12 09:30:00'));

        $this->actingAs($member)->post(route('workload.activity'), [
            'activity_date' => '2026-08-10', 'category' => 'Operasional',
            'name' => 'Peninjauan laporan', 'work_type' => 'Rutin', 'actual_minutes' => 45,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($member)->get(route('workload.entry'))
            ->assertOk()
            ->assertSee('Tanggal penginputan')
            ->assertSee('Ditentukan otomatis oleh waktu server')
            ->assertSee('Dicatat 12 Agt 2026 09:30')
            ->assertSee('Terlambat 2 hari');
    }

    public function test_member_can_record_progress_and_generate_weekly_or_monthly_report(): void
    {
        [$member, $manager, $submission] = $this->context();
        $activity = $submission->activities()->create([
            'activity_date' => '2026-08-03',
            'category' => 'Development',
            'name' => 'Organisational Development: Job Description',
            'work_type' => 'project',
            'monthly_volume' => 1,
            'unit' => 'Dokumen',
            'average_minutes_per_unit' => 60,
            'required_minutes' => 60,
        ]);
        $submission->activities()->create([
            'activity_date' => '2026-08-04',
            'category' => 'Development',
            'name' => 'Organisational Development: Job Description',
            'work_type' => 'project',
            'monthly_volume' => 2,
            'unit' => 'Dokumen',
            'average_minutes_per_unit' => 15,
            'required_minutes' => 30,
        ]);
        $submission->activities()->create([
            'activity_date' => '2026-08-04',
            'category' => 'Administrasi',
            'name' => 'Rekap absensi',
            'work_type' => 'routine',
            'monthly_volume' => 1,
            'unit' => 'Laporan',
            'average_minutes_per_unit' => 20,
            'required_minutes' => 20,
        ]);
        $base = [
            'entry_mode' => 'actual',
            'source_activity_id' => $activity->id,
            'obstacle_note' => 'Belum ada panduan baku.',
            'action_note' => 'Koordinasi dengan kepala departemen.',
        ];

        $this->actingAs($member)->post(route('workload.progress.store'), [
            ...$base,
            'report_date' => '2026-08-05',
            'status' => 'in_progress',
            'progress_summary' => 'Melakukan breakdown posisi.',
            'progress_percentage' => 60,
            'target_date' => '2026-08-20',
        ])->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('clear_draft', 'progress-actual-'.$submission->id);

        $this->actingAs($member)->post(route('workload.progress.store'), [
            'entry_mode' => 'planned',
            'category' => 'Development',
            'name' => 'Kamus Kompetensi',
            'action_note' => 'Menyusun struktur kompetensi awal.',
            'report_date' => '2026-08-12',
            'status' => 'planned',
            'progress_summary' => 'Menyusun kamus kompetensi.',
            'progress_percentage' => 0,
            'target_date' => '2026-08-25',
        ])->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('clear_draft', 'progress-planned-'.$submission->id);

        $this->assertSame(2, $submission->progressItems()->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'work_progress.created',
            'actor_id' => $member->id,
        ]);

        $weekly = $this->actingAs($manager)->get(route('reports.progress', [
            'period' => $submission->work_period_id,
            'user' => $member->id,
            'frequency' => 'weekly',
            'start_date' => '2026-08-01',
        ]));
        $weekly->assertOk()
            ->assertSee('SELESAI DIKERJAKAN')
            ->assertSee('SEDANG DIKERJAKAN')
            ->assertSee('Melakukan breakdown posisi.')
            ->assertSee('2 catatan aktivitas')
            ->assertSee('1,5 jam')
            ->assertSee('Rekap absensi')
            ->assertSee('Aktivitas aktual pada periode laporan.')
            ->assertDontSee('Menyusun kamus kompetensi.');

        $this->actingAs($manager)->get(route('reports.progress', [
            'period' => $submission->work_period_id,
            'user' => $member->id,
            'frequency' => 'monthly',
        ]))->assertOk()->assertSee('Menyusun kamus kompetensi.');
    }

    public function test_progress_validation_preserves_actual_workload_data(): void
    {
        [$member, , $submission] = $this->context();
        $activity = $submission->activities()->create([
            'activity_date' => '2026-08-10', 'name' => 'Rekonsiliasi', 'category' => 'Operasional',
            'work_type' => 'routine', 'monthly_volume' => 1, 'unit' => 'Aktivitas',
            'average_minutes_per_unit' => 60, 'required_minutes' => 60,
        ]);

        $this->actingAs($member)->post(route('workload.progress.store'), [
            'entry_mode' => 'actual',
            'source_activity_id' => $activity->id,
            'report_date' => '2026-08-10',
            'status' => 'in_progress',
            'progress_summary' => 'Menyusun draf SOP.',
            'action_note' => 'Melanjutkan review.',
            'target_date' => '2026-08-20',
            'progress_percentage' => 100,
        ])->assertSessionHasErrors('progress_percentage');

        $this->actingAs($member)->post(route('workload.progress.store'), [
            'entry_mode' => 'planned',
            'report_date' => '2026-07-31',
            'category' => 'Development',
            'name' => 'Penyusunan SOP',
            'status' => 'planned',
            'progress_summary' => 'Menyusun draf SOP.',
            'action_note' => 'Memulai penyusunan pada periode berjalan.',
            'target_date' => '2026-08-20',
            'progress_percentage' => 0,
        ])->assertSessionHasErrors('report_date');

        $this->assertDatabaseCount('work_progress_items', 0);
        $this->assertSame(60, (int) $submission->activities()->sum('required_minutes'));
    }

    public function test_member_can_create_and_reuse_normalized_master_options(): void
    {
        [$member, , $submission] = $this->context();
        $basePayload = [
            'activity_date' => '2026-08-10', 'actual_volume' => 10,
            'unit' => 'Dokumen', 'actual_minutes' => 150,
        ];

        $this->actingAs($member)->post(route('workload.activity'), [
            ...$basePayload, 'category' => 'Dukungan Strategis', 'name' => 'Koordinasi lintas unit',
            'work_type' => 'Lintas Fungsi',
        ])->assertRedirect();

        $this->actingAs($member)->post(route('workload.activity'), [
            ...$basePayload, 'category' => '  dukungan   strategis ', 'name' => 'Sinkronisasi program',
            'work_type' => 'LINTAS FUNGSI', 'activity_date' => '2026-08-11',
        ])->assertRedirect();

        $this->assertDatabaseHas('activity_master_options', [
            'type' => ActivityMasterOption::TYPE_CATEGORY, 'label' => 'Dukungan Strategis',
            'normalized_key' => 'dukungan strategis', 'created_by' => $member->id,
        ]);
        $this->assertDatabaseHas('activity_master_options', [
            'type' => ActivityMasterOption::TYPE_WORK_TYPE, 'value' => 'lintas-fungsi',
            'label' => 'Lintas Fungsi', 'created_by' => $member->id,
        ]);
        $this->assertSame(1, ActivityMasterOption::query()->where('type', 'category')->count());
        $this->assertSame(4, ActivityMasterOption::query()->where('type', 'work_type')->count());
        $this->assertSame(2, $submission->activities()->where('category', 'Dukungan Strategis')->where('work_type', 'lintas-fungsi')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'activity_master_option.created', 'actor_id' => $member->id]);

        $this->actingAs($member)->get(route('workload.entry'))
            ->assertOk()->assertSee('Dukungan Strategis')->assertSee('Lintas Fungsi');
    }

    public function test_submission_requires_capacity_and_activity(): void
    {
        [$member] = $this->context();

        $this->actingAs($member)->post(route('workload.submit'))
            ->assertSessionHasErrors('submission');
    }

    public function test_draft_submission_cannot_be_submitted_before_period_ends(): void
    {
        [$member, , $submission] = $this->context();
        $this->travelTo(Carbon::parse('2026-08-15 09:00:00'));
        $this->actingAs($member)->get(route('workload.entry'))->assertOk();
        $submission->activities()->create([
            'activity_date' => '2026-08-10', 'name' => 'Rekonsiliasi', 'category' => 'Operasional',
            'work_type' => 'routine', 'monthly_volume' => 1, 'unit' => 'Aktivitas',
            'average_minutes_per_unit' => 60, 'required_minutes' => 60,
        ]);

        $this->actingAs($member)->post(route('workload.submit'))
            ->assertSessionHasErrors('submission');

        $this->assertSame(SubmissionStatus::Draft, $submission->fresh()->status);
    }

    public function test_draft_submission_can_be_submitted_after_period_ends(): void
    {
        [$member, , $submission] = $this->context();
        $this->travelTo(Carbon::parse('2026-09-01 09:00:00'));
        $this->actingAs($member)->get(route('workload.entry'))->assertOk();
        $submission->activities()->create([
            'activity_date' => '2026-08-31', 'name' => 'Rekonsiliasi', 'category' => 'Operasional',
            'work_type' => 'routine', 'monthly_volume' => 1, 'unit' => 'Aktivitas',
            'average_minutes_per_unit' => 60, 'required_minutes' => 60,
        ]);

        $this->actingAs($member)->post(route('workload.submit'))->assertSessionHasNoErrors();

        $this->assertSame(SubmissionStatus::Submitted, $submission->fresh()->status);
    }

    public function test_revision_can_be_resubmitted_before_period_ends(): void
    {
        [$member, , $submission] = $this->context();
        $this->travelTo(Carbon::parse('2026-08-15 09:00:00'));
        $this->actingAs($member)->get(route('workload.entry'))->assertOk();
        $submission->update(['status' => SubmissionStatus::RevisionRequired]);
        $submission->activities()->create([
            'activity_date' => '2026-08-10', 'name' => 'Rekonsiliasi', 'category' => 'Operasional',
            'work_type' => 'routine', 'monthly_volume' => 1, 'unit' => 'Aktivitas',
            'average_minutes_per_unit' => 60, 'required_minutes' => 60,
        ]);

        $this->actingAs($member)->post(route('workload.submit'))->assertSessionHasNoErrors();

        $this->assertSame(SubmissionStatus::Submitted, $submission->fresh()->status);
    }

    public function test_manager_can_approve_direct_report_but_member_cannot_access_review(): void
    {
        [$member, $manager, $submission] = $this->context();
        $submission->update(['status' => SubmissionStatus::Submitted, 'submitted_at' => now()]);

        $this->actingAs($member)->get(route('reviews.index'))->assertForbidden();
        $this->actingAs($manager)->put(route('reviews.update', $submission), [
            'status' => SubmissionStatus::Approved->value, 'note' => 'Data telah diperiksa.',
        ])->assertRedirect();

        $this->assertSame(SubmissionStatus::Approved, $submission->fresh()->status);
        $this->assertDatabaseHas('validation_histories', ['to_status' => SubmissionStatus::Approved->value, 'actor_id' => $manager->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'submission.status_changed', 'actor_id' => $manager->id]);
    }

    public function test_manager_can_open_an_approved_submission_for_revision(): void
    {
        [, $manager, $submission] = $this->context();
        $submission->update([
            'status' => SubmissionStatus::Approved,
            'submitted_at' => now()->subDay(),
            'reviewed_by' => $manager->id,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($manager)->put(route('reviews.update', $submission), [
            'status' => SubmissionStatus::RevisionRequired->value,
            'note' => 'Aktivitas aktual perlu dilengkapi kembali.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(SubmissionStatus::RevisionRequired, $submission->fresh()->status);
        $this->assertDatabaseHas('validation_histories', [
            'workload_submission_id' => $submission->id,
            'from_status' => SubmissionStatus::Approved->value,
            'to_status' => SubmissionStatus::RevisionRequired->value,
            'actor_id' => $manager->id,
        ]);
    }

    private function context(): array
    {
        $unit = OrganizationalUnit::query()->create(['code' => 'OPS', 'name' => 'Operasional']);
        $manager = User::factory()->create([
            'role' => UserRole::Manager, 'organizational_unit_id' => $unit->id, 'is_active' => true,
        ]);
        $member = User::factory()->create([
            'role' => UserRole::Member, 'organizational_unit_id' => $unit->id,
            'supervisor_id' => $manager->id, 'is_active' => true,
            'cycle_work_days' => 6, 'cycle_off_days' => 1, 'daily_work_hours' => 7,
            'work_cycle_anchor_date' => '2026-08-01',
        ]);
        $period = WorkPeriod::query()->create([
            'period_start' => '2026-08-01', 'submission_deadline' => '2026-08-26',
            'status' => 'open', 'opened_by' => $manager->id, 'opened_at' => now(),
        ]);
        $submission = WorkloadSubmission::query()->create([
            'work_period_id' => $period->id, 'user_id' => $member->id, 'status' => SubmissionStatus::Draft,
        ]);

        return [$member, $manager, $submission];
    }
}

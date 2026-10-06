<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\ActivityMasterOption;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Models\WorkloadReminderDelivery;
use App\Models\WorkloadSubmission;
use App\Models\WorkPeriod;
use App\Notifications\WorkloadDeadlineReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use ZipArchive;

class OperationalAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrator_can_manage_organization(): void
    {
        [$unit, $admin, $manager] = $this->actors();

        $this->actingAs($manager)->get(route('admin.organization.index'))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.units.store'), [
            'code' => 'FIN', 'name' => 'Finance', 'is_active' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('organizational_units', ['code' => 'FIN']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'organization.unit_created', 'actor_id' => $admin->id]);
    }

    public function test_activity_master_page_is_limited_to_process_owner_and_administrator_and_shows_creator(): void
    {
        [$unit, $admin, $manager] = $this->actors();
        $member = User::factory()->create([
            'name' => 'Pembuat Opsi', 'role' => UserRole::Member, 'is_active' => true,
            'organizational_unit_id' => $unit->id,
        ]);
        ActivityMasterOption::query()->create([
            'type' => 'category', 'value' => 'Pendukung', 'label' => 'Pendukung',
            'normalized_key' => 'pendukung', 'is_active' => true, 'created_by' => $member->id,
        ]);

        $this->actingAs($manager)->get(route('admin.activity-master-options.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.activity-master-options.index'))
            ->assertOk()->assertSee('Pendukung')->assertSee('Pembuat Opsi');
    }

    public function test_admin_can_rename_and_deactivate_category_with_history_preserved(): void
    {
        [$unit, $admin, $manager] = $this->actors();
        $member = User::factory()->create([
            'role' => UserRole::Member, 'is_active' => true, 'organizational_unit_id' => $unit->id,
            'supervisor_id' => $manager->id,
        ]);
        $submission = $this->submissionFor($member);
        $option = ActivityMasterOption::query()->create([
            'type' => 'category', 'value' => 'Administratif', 'label' => 'Administratif',
            'normalized_key' => 'administratif', 'is_active' => true, 'created_by' => $member->id,
        ]);
        $activity = $submission->activities()->create($this->activityPayload('Administratif', 'routine', 'Arsip dokumen'));

        $this->actingAs($admin)->put(route('admin.activity-master-options.update', $option), [
            'label' => 'Administrasi',
        ])->assertRedirect();

        $this->assertSame('Administrasi', $option->fresh()->label);
        $this->assertSame('Administrasi', $activity->fresh()->category);
        $this->assertDatabaseHas('audit_logs', ['action' => 'activity_master_option.renamed', 'actor_id' => $admin->id]);

        $this->actingAs($admin)->put(route('admin.activity-master-options.status', $option), [
            'is_active' => 0,
        ])->assertRedirect();
        $this->assertFalse($option->fresh()->is_active);
        $this->assertSame('Administrasi', $activity->fresh()->category);
        $this->assertDatabaseHas('audit_logs', ['action' => 'activity_master_option.status_changed', 'actor_id' => $admin->id]);
    }

    public function test_admin_can_merge_work_type_and_system_rejects_activity_conflict(): void
    {
        [$unit, $admin, $manager] = $this->actors();
        $member = User::factory()->create([
            'role' => UserRole::Member, 'is_active' => true, 'organizational_unit_id' => $unit->id,
            'supervisor_id' => $manager->id,
        ]);
        $submission = $this->submissionFor($member);
        $routine = ActivityMasterOption::query()->where('type', 'work_type')->where('value', 'routine')->firstOrFail();
        $source = ActivityMasterOption::query()->create([
            'type' => 'work_type', 'value' => 'support', 'label' => 'Pendukung',
            'normalized_key' => 'pendukung', 'is_active' => true, 'created_by' => $member->id,
        ]);
        $activity = $submission->activities()->create($this->activityPayload('Operasional', 'support', 'Koordinasi'));

        $this->actingAs($admin)->post(route('admin.activity-master-options.merge', $source), [
            'target_id' => $routine->id,
        ])->assertRedirect();

        $source->refresh();
        $this->assertFalse($source->is_active);
        $this->assertSame($routine->id, $source->merged_into_id);
        $this->assertSame('routine', $activity->fresh()->work_type);
        $this->assertDatabaseHas('audit_logs', ['action' => 'activity_master_option.merged', 'actor_id' => $admin->id]);

        $conflictSource = ActivityMasterOption::query()->create([
            'type' => 'work_type', 'value' => 'coordination', 'label' => 'Koordinasi',
            'normalized_key' => 'koordinasi', 'is_active' => true, 'created_by' => $member->id,
        ]);
        $submission->activities()->create($this->activityPayload('Operasional', 'coordination', 'Aktivitas sama'));
        $submission->activities()->create($this->activityPayload('Operasional', 'routine', 'Aktivitas sama'));

        $this->actingAs($admin)->post(route('admin.activity-master-options.merge', $conflictSource), [
            'target_id' => $routine->id,
        ])->assertSessionHasErrors('target_id');

        $this->assertTrue($conflictSource->fresh()->is_active);
        $this->assertNull($conflictSource->fresh()->merged_into_id);
        $this->assertSame(1, $submission->activities()->where('work_type', 'coordination')->count());
    }

    public function test_viewer_can_open_own_input_form(): void
    {
        [$unit] = $this->actors();
        $viewer = User::factory()->create([
            'role' => UserRole::Viewer, 'is_active' => true, 'organizational_unit_id' => $unit->id,
        ]);

        $this->actingAs($viewer)->get(route('workload.entry'))->assertOk();
    }

    public function test_authenticated_pages_send_security_and_no_store_headers(): void
    {
        [, $admin] = $this->actors();

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertHeader('x-content-type-options', 'nosniff')
            ->assertHeader('x-frame-options', 'DENY')
            ->assertHeader('referrer-policy', 'same-origin')
            ->assertHeader('cache-control', 'no-store, private');
    }

    public function test_dashboard_sidebar_matches_operational_navigation_for_each_role(): void
    {
        [$unit, $admin, $manager] = $this->actors();
        $processOwner = User::factory()->create([
            'role' => UserRole::ProcessOwner,
            'is_active' => true,
            'organizational_unit_id' => $unit->id,
        ]);
        $member = User::factory()->create([
            'role' => UserRole::Member,
            'is_active' => true,
            'organizational_unit_id' => $unit->id,
        ]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Ambang utilisasi')
            ->assertSee(route('admin.activity-master-options.index'), false)
            ->assertSee(route('admin.audit.index'), false)
            ->assertSee(route('admin.organization.index'), false)
            ->assertDontSee('<span>Pengaturan</span>', false);

        $this->actingAs($processOwner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('admin.thresholds.index'), false)
            ->assertSee(route('admin.activity-master-options.index'), false)
            ->assertSee(route('admin.audit.index'), false)
            ->assertDontSee(route('admin.organization.index'), false);

        $this->actingAs($manager)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('reviews.index'), false)
            ->assertSee(route('follow-ups.index'), false)
            ->assertDontSee(route('admin.thresholds.index'), false);

        $this->actingAs($member)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('reviews.index'), false)
            ->assertDontSee(route('admin.thresholds.index'), false);
    }

    public function test_member_detail_is_limited_to_the_visible_team(): void
    {
        [$unit, , $manager] = $this->actors();
        $member = User::factory()->create([
            'role' => UserRole::Member, 'is_active' => true, 'organizational_unit_id' => $unit->id,
            'supervisor_id' => $manager->id,
        ]);
        $otherManager = User::factory()->create([
            'role' => UserRole::Manager, 'is_active' => true, 'organizational_unit_id' => $unit->id,
        ]);
        $period = WorkPeriod::query()->create([
            'period_start' => '2026-08-01', 'submission_deadline' => '2026-08-26', 'status' => 'open',
        ]);
        WorkloadSubmission::query()->create([
            'work_period_id' => $period->id, 'user_id' => $member->id, 'status' => SubmissionStatus::Approved,
        ]);

        $this->actingAs($manager)->get(route('members.show', $member))
            ->assertOk()->assertSee($member->name)->assertSee('Catatan aktivitas aktual');
        $this->actingAs($otherManager)->get(route('members.show', $member))->assertOk();
        $this->actingAs($member)->get(route('members.show', $otherManager))->assertForbidden();
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $member->id, 'action' => 'security.access_denied',
        ]);
    }

    public function test_second_period_cannot_be_opened_while_another_is_active(): void
    {
        [, $admin] = $this->actors();
        WorkPeriod::query()->create(['period_start' => '2026-08-01', 'submission_deadline' => '2026-08-26', 'status' => 'open']);
        $draft = WorkPeriod::query()->create(['period_start' => '2026-09-01', 'submission_deadline' => '2026-09-25', 'status' => 'draft']);

        $this->actingAs($admin)->post(route('admin.periods.open', $draft))->assertSessionHasErrors('period');
        $this->assertSame('draft', $draft->fresh()->status);
    }

    public function test_failed_reopen_does_not_persist_the_reason(): void
    {
        [, $admin] = $this->actors();
        WorkPeriod::query()->create([
            'period_start' => '2026-08-01', 'submission_deadline' => '2026-08-26', 'status' => 'open',
        ]);
        $locked = WorkPeriod::query()->create([
            'period_start' => '2026-07-01', 'submission_deadline' => '2026-07-26', 'status' => 'locked',
        ]);

        $this->actingAs($admin)->post(route('admin.periods.reopen', $locked), [
            'reason' => 'Koreksi data yang belum lengkap.',
        ])->assertSessionHasErrors('period');

        $this->assertSame('locked', $locked->fresh()->status);
        $this->assertNull($locked->fresh()->reopen_reason);
    }

    public function test_process_owner_can_set_capacity_standard_only_while_period_is_draft(): void
    {
        [$unit, $admin] = $this->actors();
        $processOwner = User::factory()->create([
            'role' => UserRole::ProcessOwner, 'is_active' => true, 'organizational_unit_id' => $unit->id,
        ]);
        $draft = WorkPeriod::query()->create([
            'period_start' => '2026-09-01', 'submission_deadline' => '2026-09-25', 'status' => 'draft',
        ]);
        $payload = [
            'standard_productive_percentage' => 80, 'capacity_policy_note' => 'Standar September.',
        ];

        $this->actingAs($processOwner)->put(route('admin.periods.capacity-standard.update', $draft), $payload)
            ->assertRedirect();
        $this->assertDatabaseHas('work_periods', ['id' => $draft->id, 'standard_productive_percentage' => 80]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'period.capacity_standard_updated', 'actor_id' => $processOwner->id,
        ]);

        $draft->update(['status' => 'open']);
        $this->actingAs($admin)->put(route('admin.periods.capacity-standard.update', $draft), [
            ...$payload, 'standard_productive_percentage' => 75,
        ])->assertSessionHasErrors('capacity_standard');
        $this->assertEquals(80, $draft->fresh()->standard_productive_percentage);
    }

    public function test_process_owner_can_update_period_data_only_while_period_is_draft(): void
    {
        [$unit, $admin] = $this->actors();
        $processOwner = User::factory()->create([
            'role' => UserRole::ProcessOwner, 'is_active' => true, 'organizational_unit_id' => $unit->id,
        ]);
        $draft = WorkPeriod::query()->create([
            'period_start' => '2026-09-01', 'submission_deadline' => '2026-09-25', 'status' => 'draft',
        ]);

        $this->actingAs($processOwner)->put(route('admin.periods.update', $draft), [
            'period_start' => '2026-10-01', 'submission_deadline' => '2026-10-26',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $draft->refresh();
        $this->assertSame('2026-10-01', $draft->period_start->toDateString());
        $this->assertSame('2026-10-26', $draft->submission_deadline->toDateString());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'period.updated', 'actor_id' => $processOwner->id,
        ]);

        $draft->update(['status' => 'open']);
        $this->actingAs($admin)->put(route('admin.periods.update', $draft), [
            'period_start' => '2026-11-01', 'submission_deadline' => '2026-11-25',
        ])->assertSessionHasErrors('period');

        $this->assertSame('2026-10-01', $draft->fresh()->period_start->toDateString());
    }

    public function test_period_update_requires_first_day_of_month_and_unique_period(): void
    {
        [, $admin] = $this->actors();
        WorkPeriod::query()->create([
            'period_start' => '2026-08-01', 'submission_deadline' => '2026-08-26', 'status' => 'draft',
        ]);
        $draft = WorkPeriod::query()->create([
            'period_start' => '2026-09-01', 'submission_deadline' => '2026-09-25', 'status' => 'draft',
        ]);

        $this->actingAs($admin)->put(route('admin.periods.update', $draft), [
            'period_start' => '2026-09-02', 'submission_deadline' => '2026-09-25',
        ])->assertSessionHasErrors('period_start');

        $this->actingAs($admin)->put(route('admin.periods.update', $draft), [
            'period_start' => '2026-08-01', 'submission_deadline' => '2026-08-26',
        ])->assertSessionHasErrors('period_start');

        $this->assertSame('2026-09-01', $draft->fresh()->period_start->toDateString());
    }

    public function test_threshold_cannot_be_applied_retroactively(): void
    {
        [, $admin] = $this->actors();

        $this->actingAs($admin)->post(route('admin.thresholds.store'), [
            'available_below' => 70, 'healthy_up_to' => 85, 'dense_up_to' => 100,
            'effective_from' => today()->subDay()->toDateString(), 'is_provisional' => 1, 'change_reason' => 'Uji perubahan ambang.',
        ])->assertSessionHasErrors('effective_from');
    }

    public function test_csv_export_neutralizes_spreadsheet_formulas(): void
    {
        [$unit, , $manager] = $this->actors();
        $member = User::factory()->create([
            'name' => '=HYPERLINK("bad")', 'role' => UserRole::Member, 'is_active' => true,
            'organizational_unit_id' => $unit->id, 'supervisor_id' => $manager->id,
        ]);
        $period = WorkPeriod::query()->create(['period_start' => '2026-08-01', 'submission_deadline' => '2026-08-26', 'status' => 'open']);
        WorkloadSubmission::query()->create(['work_period_id' => $period->id, 'user_id' => $member->id, 'status' => SubmissionStatus::Approved]);

        $response = $this->actingAs($manager)->get(route('reports.csv'));

        $response->assertOk();
        $this->assertStringContainsString("'=HYPERLINK", $response->streamedContent());
    }

    public function test_xlsx_export_is_a_valid_openxml_archive(): void
    {
        [$unit, , $manager] = $this->actors();
        $member = User::factory()->create([
            'name' => 'Budi Santoso', 'employee_code' => 'EMP-009', 'role' => UserRole::Member,
            'is_active' => true, 'organizational_unit_id' => $unit->id, 'supervisor_id' => $manager->id,
        ]);
        $period = WorkPeriod::query()->create([
            'period_start' => '2026-08-01', 'submission_deadline' => '2026-08-26', 'status' => 'open',
        ]);
        WorkloadSubmission::query()->create([
            'work_period_id' => $period->id, 'user_id' => $member->id, 'status' => SubmissionStatus::Approved,
        ]);

        $response = $this->actingAs($manager)->get(route('reports.xlsx'));

        $response->assertOk()->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $this->assertNotFalse($zip->locateName('[Content_Types].xml'));
        $this->assertNotFalse($zip->locateName('xl/workbook.xml'));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertIsString($sheet);
        $this->assertStringContainsString('Budi Santoso', $sheet);
        $zip->close();
    }

    public function test_dashboard_and_export_apply_the_same_authorized_filters(): void
    {
        [$unit, $admin] = $this->actors();
        $otherUnit = OrganizationalUnit::query()->create([
            'code' => 'FIN', 'name' => 'Finance', 'is_active' => true,
        ]);
        $operationsMember = User::factory()->create([
            'name' => 'Anggota Operasional', 'role' => UserRole::Member, 'is_active' => true,
            'organizational_unit_id' => $unit->id,
        ]);
        $financeMember = User::factory()->create([
            'name' => 'Anggota Finance', 'role' => UserRole::Member, 'is_active' => true,
            'organizational_unit_id' => $otherUnit->id,
        ]);
        $period = WorkPeriod::query()->create([
            'period_start' => '2026-08-01', 'submission_deadline' => '2026-08-26', 'status' => 'open',
        ]);
        $filters = ['period' => $period->id, 'unit' => $unit->id, 'status' => 'unavailable'];

        $this->actingAs($admin)->get(route('dashboard', $filters))
            ->assertOk()->assertSee($operationsMember->name)->assertDontSee($financeMember->name);

        $csv = $this->actingAs($admin)->get(route('reports.csv', $filters));
        $csv->assertOk();
        $content = $csv->streamedContent();
        $this->assertStringContainsString($operationsMember->name, $content);
        $this->assertStringNotContainsString($financeMember->name, $content);
    }

    public function test_unit_filter_outside_user_scope_is_forbidden_and_audited(): void
    {
        [, , $manager] = $this->actors();
        $otherUnit = OrganizationalUnit::query()->create([
            'code' => 'FIN', 'name' => 'Finance', 'is_active' => true,
        ]);
        WorkPeriod::query()->create([
            'period_start' => '2026-08-01', 'submission_deadline' => '2026-08-26', 'status' => 'open',
        ]);

        $this->actingAs($manager)->get(route('dashboard', ['unit' => $otherUnit->id]))->assertForbidden();
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $manager->id, 'action' => 'security.access_denied',
        ]);
    }

    public function test_reminder_command_is_idempotent_per_day(): void
    {
        Notification::fake();
        [$unit] = $this->actors();
        $member = User::factory()->create(['role' => UserRole::Member, 'is_active' => true, 'organizational_unit_id' => $unit->id]);
        WorkPeriod::query()->create([
            'period_start' => today()->startOfMonth(), 'submission_deadline' => today()->addDays(3), 'status' => 'open',
        ]);

        $this->artisan('workload:send-reminders')->assertSuccessful();
        $this->artisan('workload:send-reminders')->assertSuccessful();

        Notification::assertSentToTimes($member, WorkloadDeadlineReminder::class, 1);
        $this->assertSame(1, WorkloadReminderDelivery::query()->where('user_id', $member->id)->count());
    }

    private function submissionFor(User $member): WorkloadSubmission
    {
        $period = WorkPeriod::query()->create([
            'period_start' => '2026-08-01', 'submission_deadline' => '2026-08-26', 'status' => 'open',
        ]);

        return WorkloadSubmission::query()->create([
            'work_period_id' => $period->id, 'user_id' => $member->id, 'status' => SubmissionStatus::Draft,
        ]);
    }

    private function activityPayload(string $category, string $workType, string $name): array
    {
        return [
            'category' => $category, 'name' => $name, 'work_type' => $workType,
            'monthly_volume' => 1, 'unit' => 'Dokumen', 'average_minutes_per_unit' => 15,
            'required_minutes' => 15,
        ];
    }

    private function actors(): array
    {
        $unit = OrganizationalUnit::query()->create(['code' => 'OPS', 'name' => 'Operasional', 'is_active' => true]);
        $admin = User::factory()->create(['role' => UserRole::Administrator, 'is_active' => true, 'organizational_unit_id' => $unit->id]);
        $manager = User::factory()->create(['role' => UserRole::Manager, 'is_active' => true, 'organizational_unit_id' => $unit->id]);

        return [$unit, $admin, $manager];
    }
}

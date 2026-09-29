<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FollowUpAction;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyFollowUpTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_only_sees_follow_up_actions_assigned_to_them(): void
    {
        [$unit, $owner, $otherUser] = $this->context();
        $ownAction = $this->createAction($unit, $owner, 'Tugas milik saya');
        $otherAction = $this->createAction($unit, $otherUser, 'Tugas pengguna lain');

        $this->actingAs($owner)->get(route('follow-ups.mine'))
            ->assertOk()
            ->assertSee('Tindak lanjut saya')
            ->assertSee($ownAction->title)
            ->assertDontSee($otherAction->title);
    }

    public function test_owner_can_update_only_status_and_change_is_audited(): void
    {
        [$unit, $owner, $otherUser] = $this->context();
        $action = $this->createAction($unit, $owner, 'Evaluasi alur kerja');

        $this->actingAs($owner)->patch(route('follow-ups.mine.update', $action), [
            'status' => 'in_progress',
            'title' => 'Judul yang dimanipulasi',
            'owner_id' => $otherUser->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $action->refresh();
        $this->assertSame('in_progress', $action->status);
        $this->assertSame('Evaluasi alur kerja', $action->title);
        $this->assertSame($owner->id, $action->owner_id);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'follow_up.owner_status_updated',
            'actor_id' => $owner->id,
            'auditable_id' => $action->id,
        ]);
    }

    public function test_user_cannot_update_another_users_follow_up_action(): void
    {
        [$unit, $owner, $otherUser] = $this->context();
        $action = $this->createAction($unit, $otherUser, 'Tugas pengguna lain');

        $this->actingAs($owner)->patch(route('follow-ups.mine.update', $action), [
            'status' => 'completed',
        ])->assertForbidden();

        $this->assertSame('open', $action->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'security.access_denied',
            'actor_id' => $owner->id,
        ]);
    }

    public function test_owner_cannot_cancel_an_action_or_update_one_cancelled_by_management(): void
    {
        [$unit, $owner] = $this->context();
        $action = $this->createAction($unit, $owner, 'Tugas aktif');

        $this->actingAs($owner)->patch(route('follow-ups.mine.update', $action), [
            'status' => 'cancelled',
        ])->assertSessionHasErrors('status');
        $this->assertSame('open', $action->fresh()->status);

        $action->update(['status' => 'cancelled']);
        $this->actingAs($owner)->patch(route('follow-ups.mine.update', $action), [
            'status' => 'in_progress',
        ])->assertUnprocessable();
        $this->assertSame('cancelled', $action->fresh()->status);
    }

    public function test_dashboard_exposes_my_follow_up_link_and_open_count(): void
    {
        [$unit, $owner] = $this->context();
        $this->createAction($unit, $owner, 'Tugas aktif');

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('follow-ups.mine'), false)
            ->assertSee('Tindak lanjut saya')
            ->assertSee('<span class="nav-count">1</span>', false);
    }

    private function createAction(OrganizationalUnit $unit, User $owner, string $title): FollowUpAction
    {
        return FollowUpAction::query()->create([
            'organizational_unit_id' => $unit->id,
            'action_type' => 'process_improvement',
            'title' => $title,
            'description' => 'Deskripsi tindak lanjut.',
            'owner_id' => $owner->id,
            'target_date' => today()->addDay(),
            'status' => 'open',
        ]);
    }

    private function context(): array
    {
        $unit = OrganizationalUnit::query()->create([
            'code' => 'OPS', 'name' => 'Operasional', 'is_active' => true,
        ]);
        $owner = User::factory()->create([
            'role' => UserRole::Member, 'organizational_unit_id' => $unit->id, 'is_active' => true,
        ]);
        $otherUser = User::factory()->create([
            'role' => UserRole::Member, 'organizational_unit_id' => $unit->id, 'is_active' => true,
        ]);

        return [$unit, $owner, $otherUser];
    }
}

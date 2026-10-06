<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\VisibleTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisibleTeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_only_see_members_in_the_same_unit(): void
    {
        $unit = OrganizationalUnit::query()->create(['code' => 'GEN', 'name' => 'Generalist', 'is_active' => true]);
        $otherUnit = OrganizationalUnit::query()->create(['code' => 'HI', 'name' => 'HI', 'is_active' => true]);
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor, 'is_active' => true, 'organizational_unit_id' => $unit->id]);
        $member = User::factory()->create([
            'role' => UserRole::Member,
            'is_active' => true,
            'organizational_unit_id' => $unit->id,
            'supervisor_id' => $supervisor->id,
        ]);
        $otherMember = User::factory()->create([
            'role' => UserRole::Member, 'is_active' => true,
            'organizational_unit_id' => $otherUnit->id, 'supervisor_id' => $supervisor->id,
        ]);
        User::factory()->create(['role' => UserRole::Member, 'is_active' => false, 'organizational_unit_id' => $unit->id]);
        User::factory()->create(['role' => UserRole::Manager, 'is_active' => true, 'organizational_unit_id' => $unit->id]);

        $this->assertEqualsCanonicalizing([$supervisor->id, $member->id], (new VisibleTeam)->users($supervisor)->pluck('id')->all());
        $this->actingAs($supervisor)->get(route('dashboard'))->assertOk();
        $this->get(route('members.show', $member))->assertOk();
        $this->get(route('members.show', $otherMember))->assertForbidden();
        $this->get(route('reviews.index'))->assertForbidden();
    }

    public function test_supervisor_without_unit_can_only_see_self(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor, 'is_active' => true]);
        User::factory()->create(['role' => UserRole::Member, 'is_active' => true]);

        $this->assertSame([$supervisor->id], (new VisibleTeam)->users($supervisor)->pluck('id')->all());
    }

    public function test_manager_can_see_supervisors_and_members_across_units(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager, 'is_active' => true]);
        $expected = [$manager->id];
        foreach (['HI', 'GEN'] as $code) {
            $unit = OrganizationalUnit::query()->create(['code' => $code, 'name' => $code, 'is_active' => true]);
            $supervisor = User::factory()->create(['role' => UserRole::Supervisor, 'is_active' => true, 'organizational_unit_id' => $unit->id]);
            $member = User::factory()->create(['role' => UserRole::Member, 'is_active' => true, 'organizational_unit_id' => $unit->id, 'supervisor_id' => $supervisor->id]);
            array_push($expected, $supervisor->id, $member->id);
            $this->actingAs($manager)->get(route('members.show', $member))->assertOk();
            $this->get(route('members.show', $supervisor))->assertOk();
        }
        User::factory()->create(['role' => UserRole::Administrator, 'is_active' => true]);
        User::factory()->create(['role' => UserRole::Member, 'is_active' => false]);

        $this->assertEqualsCanonicalizing($expected, (new VisibleTeam)->users($manager)->pluck('id')->all());
    }

    public function test_every_role_has_a_visible_team_scope(): void
    {
        foreach (UserRole::cases() as $role) {
            $actor = User::factory()->create(['role' => $role, 'is_active' => true]);

            $this->assertIsArray((new VisibleTeam)->users($actor)->pluck('id')->all());
        }
    }
}

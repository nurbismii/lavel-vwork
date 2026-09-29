<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_profile(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->patch(route('profile.update'), [])->assertRedirect(route('login'));
        $this->put(route('profile.password.update'), [])->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_open_profile_page(): void
    {
        $user = User::factory()->create([
            'name' => 'Pengguna RuangKerja',
            'email' => 'pengguna@example.com',
        ]);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Profil dan keamanan')
            ->assertSee('Pengguna RuangKerja')
            ->assertSee('pengguna@example.com')
            ->assertSee(route('profile.password.update'), false);
    }

    public function test_user_can_update_name_and_email(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => '  Nama Pengguna  ',
            'email' => '  PENGGUNA@EXAMPLE.COM  ',
        ])->assertRedirect(route('profile.edit'))->assertSessionHas('profile_success');

        $user->refresh();
        $this->assertSame('Nama Pengguna', $user->name);
        $this->assertSame('pengguna@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => 'profile.updated',
        ]);
    }

    public function test_profile_email_must_be_unique(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Nama Baru',
            'email' => $other->email,
        ])->assertSessionHasErrors('email', errorBag: 'profileUpdate');

        $this->assertNotSame($other->email, $user->fresh()->email);
    }

    public function test_user_can_update_password_with_current_password(): void
    {
        $user = User::factory()->create(['password' => 'password-lama']);

        $this->actingAs($user)->put(route('profile.password.update'), [
            'current_password' => 'password-lama',
            'password' => 'password-baru-aman',
            'password_confirmation' => 'password-baru-aman',
        ])->assertRedirect(route('profile.edit'))->assertSessionHas('password_success');

        $this->assertTrue(Hash::check('password-baru-aman', $user->fresh()->password));
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => 'profile.password_updated',
        ]);
    }

    public function test_wrong_current_password_does_not_change_password(): void
    {
        $user = User::factory()->create(['password' => 'password-lama']);

        $this->actingAs($user)->put(route('profile.password.update'), [
            'current_password' => 'salah-password',
            'password' => 'password-baru-aman',
            'password_confirmation' => 'password-baru-aman',
        ])->assertSessionHasErrors('current_password', errorBag: 'passwordUpdate');

        $this->assertTrue(Hash::check('password-lama', $user->fresh()->password));
    }
}

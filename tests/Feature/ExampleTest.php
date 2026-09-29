<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create(['email' => 'inactive@example.com', 'is_active' => false, 'role' => UserRole::Member]);

        $this->post('/login', ['email' => 'inactive@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');
    }
}

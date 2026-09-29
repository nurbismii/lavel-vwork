<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['auth.bootstrap_admin' => [
            'name' => 'Admin Production',
            'email' => 'admin@example.com',
            'password' => 'Test-Only-Secret-123!',
        ]]);
    }

    public function test_bootstrap_creates_only_admin_and_preserves_existing_account(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->sole();
        $this->assertSame(UserRole::Administrator, $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check('Test-Only-Secret-123!', $admin->password));
        foreach (['organizational_units', 'work_periods', 'workload_submissions', 'utilization_thresholds'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->post('/login', ['email' => $admin->email, 'password' => 'Test-Only-Secret-123!'])->assertRedirect('/');
        $this->get('/')->assertOk();
        $this->get('/admin/organisasi')->assertOk();

        $admin->update(['name' => 'Updated Admin', 'password' => 'Changed-Secret-456!']);
        $before = $admin->fresh()->getAttributes();
        config(['auth.bootstrap_admin.password' => null]);
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($before, $admin->fresh()->getAttributes());
        $this->assertDatabaseCount('users', 1);
    }

    public function test_missing_or_weak_credentials_do_not_create_users(): void
    {
        foreach ([['email', null], ['email', 'invalid'], ['password', null], ['password', 'password']] as [$field, $value]) {
            $original = config('auth.bootstrap_admin.'.$field);
            config(['auth.bootstrap_admin.'.$field => $value]);
            try {
                $this->seed(DatabaseSeeder::class);
                $this->fail('Invalid bootstrap credentials must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($field, $exception->errors());
                $this->assertDatabaseCount('users', 0);
            }
            config(['auth.bootstrap_admin.'.$field => $original]);
        }
    }

    public function test_existing_member_or_inactive_admin_is_never_changed(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com', 'role' => UserRole::Member]);
        foreach ([[UserRole::Member, true], [UserRole::Administrator, false]] as [$role, $active]) {
            $user->update(['role' => $role, 'is_active' => $active]);
            $before = $user->fresh()->getAttributes();
            try {
                $this->seed(DatabaseSeeder::class);
                $this->fail('Conflicting account must be rejected.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('Akun tidak diubah', $exception->getMessage());
                $this->assertSame($before, $user->fresh()->getAttributes());
            }
        }
    }

    public function test_demo_seeder_is_blocked_in_production(): void
    {
        $this->app->instance('env', 'production');
        try {
            (new DemoSeeder)->run();
            $this->fail('Demo seeding must be blocked.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('local/testing', $exception->getMessage());
            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseCount('organizational_units', 0);
        }
    }
}

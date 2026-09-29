<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $account = Validator::make(config('auth.bootstrap_admin'), [
            'email' => ['required', 'email', 'max:255'],
        ])->validate();

        $existing = User::query()->where('email', $account['email'])->first();

        if ($existing) {
            if ($existing->role !== UserRole::Administrator || ! $existing->is_active) {
                throw new RuntimeException('Email bootstrap sudah digunakan akun non-administrator atau nonaktif. Akun tidak diubah.');
            }

            $this->command?->info('Administrator sudah tersedia. Akun dan password tidak diubah.');

            return;
        }

        $account = Validator::make(config('auth.bootstrap_admin'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:72', Password::min(12)->mixedCase()->numbers()->symbols()],
        ])->validate();

        User::query()->firstOrCreate(['email' => $account['email']], [
            'name' => $account['name'],
            'password' => $account['password'],
            'position' => 'Administrator',
            'role' => UserRole::Administrator,
            'is_active' => true,
        ]);

        $this->command?->info('Administrator tersedia. Buat unit dan pengguna melalui menu organisasi.');
    }
}

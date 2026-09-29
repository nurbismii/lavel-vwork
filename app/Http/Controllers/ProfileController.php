<?php

namespace App\Http\Controllers;

use App\Http\Requests\PasswordUpdateRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.edit');
    }

    public function update(ProfileUpdateRequest $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        $before = $user->only(['name', 'email']);
        $values = $request->validated();

        DB::transaction(function () use ($user, $values, $before, $audit, $request): void {
            if ($user->email !== $values['email']) {
                $user->email_verified_at = null;
            }

            $user->fill($values)->save();
            $audit->record('profile.updated', $user, $before, $user->fresh()->only(['name', 'email']), $request);
        });

        return to_route('profile.edit')->with('profile_success', 'Nama dan email berhasil diperbarui.');
    }

    public function updatePassword(PasswordUpdateRequest $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user, $request, $audit): void {
            $user->forceFill([
                'password' => $request->validated('password'),
                'remember_token' => Str::random(60),
            ])->save();

            $audit->record('profile.password_updated', $user, null, null, $request);
        });

        $request->session()->regenerate();

        return to_route('profile.edit')->with('password_success', 'Kata sandi berhasil diperbarui.');
    }
}

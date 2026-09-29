<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrganizationalUnitRequest;
use App\Http\Requests\UserManagementRequest;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()->with(['organizationalUnit', 'supervisor'])
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($nested) => $nested
                ->where('name', 'like', '%'.$request->string('search').'%')
                ->orWhere('email', 'like', '%'.$request->string('search').'%')))
            ->when($request->filled('unit'), fn ($query) => $query->where('organizational_unit_id', $request->integer('unit')))
            ->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.organization', [
            'users' => $users,
            'units' => OrganizationalUnit::query()->orderBy('name')->get(),
            'supervisors' => User::query()->whereIn('role', [UserRole::Manager, UserRole::ProcessOwner, UserRole::Administrator])->where('is_active', true)->orderBy('name')->get(),
            'roles' => UserRole::cases(),
        ]);
    }

    public function storeUnit(OrganizationalUnitRequest $request, AuditLogger $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $audit) {
            $unit = OrganizationalUnit::query()->create($request->validated());
            $audit->record('organization.unit_created', $unit, null, $unit->toArray(), $request);
        });

        return back()->with('success', 'Unit berhasil ditambahkan.');
    }

    public function updateUnit(OrganizationalUnitRequest $request, OrganizationalUnit $unit, AuditLogger $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $unit, $audit) {
            $before = $unit->toArray();
            $unit->update($request->validated());
            $audit->record('organization.unit_updated', $unit, $before, $unit->fresh()->toArray(), $request);
        });

        return back()->with('success', 'Unit berhasil diperbarui.');
    }

    public function storeUser(UserManagementRequest $request, AuditLogger $audit): RedirectResponse
    {
        $values = $this->validatedUserValues($request);
        $user = DB::transaction(function () use ($values, $audit, $request) {
            $user = User::query()->create($values);
            $audit->record('organization.user_created', $user, null, $user->toArray(), $request);

            return $user;
        });

        return back()->with('success', "Akun {$user->name} berhasil dibuat.");
    }

    public function updateUser(UserManagementRequest $request, User $user, AuditLogger $audit): RedirectResponse
    {
        if ($user->is($request->user()) && ($request->input('role') !== UserRole::Administrator->value || ! $request->boolean('is_active'))) {
            throw ValidationException::withMessages(['user' => 'Administrator tidak dapat menonaktifkan atau menurunkan role akunnya sendiri.']);
        }

        $values = $this->validatedUserValues($request);
        $before = $user->toArray();
        DB::transaction(function () use ($user, $values, $before, $audit, $request) {
            $user->update($values);
            $audit->record('organization.user_updated', $user, $before, $user->fresh()->toArray(), $request);
        });

        return back()->with('success', 'Akun pengguna berhasil diperbarui.');
    }

    private function validatedUserValues(UserManagementRequest $request): array
    {
        $values = $request->validated();
        if (filled($values['supervisor_id'] ?? null)) {
            $supervisorUnit = User::query()->whereKey($values['supervisor_id'])->value('organizational_unit_id');
            if ((int) $supervisorUnit !== (int) $values['organizational_unit_id']) {
                throw ValidationException::withMessages(['supervisor_id' => 'Atasan harus berada pada unit yang sama.']);
            }
        }
        if (filled($values['password'] ?? null)) {
            $values['password'] = Hash::make($values['password']);
        } else {
            unset($values['password']);
        }
        unset($values['password_confirmation']);

        return $values;
    }
}

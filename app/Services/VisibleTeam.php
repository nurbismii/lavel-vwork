<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class VisibleTeam
{
    public function users(User $actor): Builder
    {
        $query = User::query()->where('is_active', true);

        return match ($actor->role) {
            UserRole::Member => $query->whereKey($actor->id),
            UserRole::Supervisor => $query->where(fn (Builder $team) => $team
                ->whereKey($actor->id)
                ->when($actor->organizational_unit_id !== null, fn (Builder $members) => $members
                    ->orWhere(fn (Builder $unit) => $unit
                        ->where('organizational_unit_id', $actor->organizational_unit_id)
                        ->where('role', UserRole::Member)))),
            UserRole::Manager => $query->whereIn('role', [UserRole::Supervisor, UserRole::Member]),
            UserRole::ProcessOwner, UserRole::Viewer => $query->where('organizational_unit_id', $actor->organizational_unit_id),
            UserRole::Administrator => $query,
        };
    }
}

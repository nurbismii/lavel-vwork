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
            UserRole::Manager => $query->where('supervisor_id', $actor->id),
            UserRole::ProcessOwner, UserRole::Viewer => $query->where('organizational_unit_id', $actor->organizational_unit_id),
            UserRole::Administrator => $query,
        };
    }
}

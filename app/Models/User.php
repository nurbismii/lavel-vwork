<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'organizational_unit_id', 'supervisor_id',
        'employee_code', 'position', 'role', 'is_active', 'cycle_work_days',
        'cycle_off_days', 'daily_work_hours', 'work_cycle_anchor_date',
    ];

    protected $hidden = ['password', 'remember_token'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'daily_work_hours' => 'decimal:2',
            'work_cycle_anchor_date' => 'date',
        ];
    }

    public function organizationalUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supervisor_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'supervisor_id');
    }

    public function workloadSubmissions(): HasMany
    {
        return $this->hasMany(WorkloadSubmission::class);
    }

    public function workProgressItems(): HasManyThrough
    {
        return $this->hasManyThrough(
            WorkProgressItem::class,
            WorkloadSubmission::class,
            'user_id',
            'workload_submission_id',
        );
    }

    public function assignedFollowUpActions(): HasMany
    {
        return $this->hasMany(FollowUpAction::class, 'owner_id');
    }

    public function hasAnyRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }
}

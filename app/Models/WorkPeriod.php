<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkPeriod extends Model
{
    protected $fillable = [
        'period_start', 'submission_deadline', 'status', 'opened_by', 'opened_at',
        'locked_by', 'locked_at', 'reopen_reason', 'standard_work_days',
        'standard_hours_per_day', 'standard_productive_percentage', 'capacity_policy_note',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'submission_deadline' => 'date',
            'opened_at' => 'datetime',
            'locked_at' => 'datetime',
            'standard_hours_per_day' => 'decimal:2',
            'standard_productive_percentage' => 'decimal:2',
        ];
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(WorkloadSubmission::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function hasEnded(): bool
    {
        return today()->greaterThan($this->period_start->copy()->endOfMonth());
    }
}

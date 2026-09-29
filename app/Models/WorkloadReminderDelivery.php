<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkloadReminderDelivery extends Model
{
    protected $fillable = [
        'work_period_id', 'user_id', 'reminder_date', 'channel', 'status',
        'queued_at', 'sent_at', 'failure_reason',
    ];

    protected function casts(): array
    {
        return ['reminder_date' => 'date', 'queued_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function workPeriod(): BelongsTo
    {
        return $this->belongsTo(WorkPeriod::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

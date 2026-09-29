<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkloadCapacity extends Model
{
    protected $fillable = [
        'workload_submission_id', 'work_days', 'cycle_work_days', 'cycle_off_days',
        'hours_per_day', 'work_cycle_anchor_date',
        'productive_percentage', 'gross_minutes', 'effective_minutes',
    ];

    protected function casts(): array
    {
        return [
            'hours_per_day' => 'decimal:2',
            'productive_percentage' => 'decimal:2',
            'work_cycle_anchor_date' => 'date',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(WorkloadSubmission::class, 'workload_submission_id');
    }
}

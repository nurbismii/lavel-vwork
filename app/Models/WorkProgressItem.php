<?php

namespace App\Models;

use App\Enums\ProgressStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkProgressItem extends Model
{
    protected $fillable = [
        'workload_submission_id', 'source_activity_id', 'report_date', 'category', 'name', 'status',
        'progress_summary', 'obstacle_note', 'action_note', 'target_date',
        'progress_percentage',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'target_date' => 'date',
            'status' => ProgressStatus::class,
            'progress_percentage' => 'integer',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(WorkloadSubmission::class, 'workload_submission_id');
    }

    public function sourceActivity(): BelongsTo
    {
        return $this->belongsTo(WorkloadActivity::class, 'source_activity_id');
    }
}

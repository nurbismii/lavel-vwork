<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowUpAction extends Model
{
    protected $fillable = [
        'workload_submission_id', 'organizational_unit_id', 'action_type', 'title',
        'description', 'owner_id', 'target_date', 'status',
    ];

    protected function casts(): array
    {
        return ['target_date' => 'date'];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(WorkloadSubmission::class, 'workload_submission_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function organizationalUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }
}

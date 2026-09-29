<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValidationHistory extends Model
{
    public $timestamps = false;

    protected $fillable = ['workload_submission_id', 'from_status', 'to_status', 'note', 'actor_id', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(WorkloadSubmission::class, 'workload_submission_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

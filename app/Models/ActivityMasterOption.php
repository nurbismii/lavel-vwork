<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityMasterOption extends Model
{
    public const TYPE_CATEGORY = 'category';

    public const TYPE_WORK_TYPE = 'work_type';

    protected $fillable = [
        'type', 'value', 'label', 'normalized_key', 'is_active', 'created_by',
        'merged_into_id', 'merged_by', 'merged_at',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'merged_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    public function merger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merged_by');
    }
}

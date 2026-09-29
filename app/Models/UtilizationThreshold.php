<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UtilizationThreshold extends Model
{
    protected $fillable = [
        'available_below', 'healthy_up_to', 'dense_up_to', 'effective_from',
        'is_provisional', 'created_by', 'change_reason',
    ];

    protected function casts(): array
    {
        return [
            'available_below' => 'decimal:2',
            'healthy_up_to' => 'decimal:2',
            'dense_up_to' => 'decimal:2',
            'effective_from' => 'date',
            'is_provisional' => 'boolean',
        ];
    }

    public static function effectiveFor(WorkPeriod $period): self
    {
        return self::query()
            ->whereDate('effective_from', '<=', $period->period_start)
            ->latest('effective_from')
            ->firstOrNew([], [
                'available_below' => 70,
                'healthy_up_to' => 85,
                'dense_up_to' => 100,
                'is_provisional' => true,
            ]);
    }
}

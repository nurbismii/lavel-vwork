<?php

namespace App\Models;

use App\Enums\WorkType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WorkloadActivity extends Model
{
    protected $fillable = [
        'workload_submission_id', 'activity_date', 'category', 'name', 'work_type', 'monthly_volume',
        'unit', 'average_minutes_per_unit', 'required_minutes', 'exception_reason',
    ];

    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
            'monthly_volume' => 'decimal:2',
            'average_minutes_per_unit' => 'decimal:2',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(WorkloadSubmission::class, 'workload_submission_id');
    }

    public function workTypeOption(): HasOne
    {
        return $this->hasOne(ActivityMasterOption::class, 'value', 'work_type')
            ->where('type', ActivityMasterOption::TYPE_WORK_TYPE);
    }

    public function workTypeLabel(): string
    {
        return $this->workTypeOption?->label
            ?? WorkType::tryFrom($this->work_type)?->label()
            ?? $this->work_type;
    }

    public function inputDelayDays(): ?int
    {
        if (! $this->activity_date || ! $this->created_at) {
            return null;
        }

        return max(0, (int) $this->activity_date->copy()->startOfDay()
            ->diffInDays($this->created_at->copy()->startOfDay(), false));
    }

    public function wasEnteredLate(): bool
    {
        return ($this->inputDelayDays() ?? 0) > 0;
    }

    public function timelinessLabel(): string
    {
        $delayDays = $this->inputDelayDays();

        return match (true) {
            $delayDays === null => 'Tidak dinilai',
            $delayDays > 0 => 'Terlambat '.$delayDays.' hari',
            default => 'Tepat waktu',
        };
    }

    public function timelinessClass(): string
    {
        return match (true) {
            $this->inputDelayDays() === null => 'not-rated',
            $this->wasEnteredLate() => 'late',
            default => 'on-time',
        };
    }
}

<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WorkloadSubmission extends Model
{
    protected $fillable = [
        'work_period_id', 'user_id', 'status', 'member_note', 'review_note',
        'submitted_at', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function workPeriod(): BelongsTo
    {
        return $this->belongsTo(WorkPeriod::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function capacity(): HasOne
    {
        return $this->hasOne(WorkloadCapacity::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(WorkloadActivity::class)
            ->orderByDesc('activity_date')
            ->orderByDesc('created_at');
    }

    public function progressItems(): HasMany
    {
        return $this->hasMany(WorkProgressItem::class)
            ->orderByDesc('report_date')
            ->orderByDesc('created_at');
    }

    public function validationHistories(): HasMany
    {
        return $this->hasMany(ValidationHistory::class);
    }

    public function followUpActions(): HasMany
    {
        return $this->hasMany(FollowUpAction::class);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [SubmissionStatus::Draft, SubmissionStatus::RevisionRequired], true)
            && $this->workPeriod->isOpen();
    }

    public function canBeSubmitted(): bool
    {
        if (! $this->isEditable()) {
            return false;
        }

        return $this->status === SubmissionStatus::RevisionRequired
            || $this->workPeriod->hasEnded();
    }
}

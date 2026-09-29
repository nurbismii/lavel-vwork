<?php

namespace App\Services;

use App\Enums\WorkloadStatus;
use App\Models\UtilizationThreshold;
use App\Models\WorkloadSubmission;

class WorkloadCalculator
{
    /** @return array{gross_minutes:int,effective_minutes:int} */
    public function capacity(int $workDays, float $hoursPerDay, float $productivePercentage): array
    {
        $grossMinutes = (int) round($workDays * $hoursPerDay * 60);

        return [
            'gross_minutes' => $grossMinutes,
            'effective_minutes' => (int) round($grossMinutes * ($productivePercentage / 100)),
        ];
    }

    public function requiredMinutes(float $monthlyVolume, float $averageMinutesPerUnit): int
    {
        return (int) round($monthlyVolume * $averageMinutesPerUnit);
    }

    /** @return array{effective_minutes:int,required_minutes:int,utilization:?float,status:WorkloadStatus,by_type:array<string,int>} */
    public function summarize(WorkloadSubmission $submission, UtilizationThreshold $threshold): array
    {
        $submission->loadMissing(['capacity', 'activities']);
        $effectiveMinutes = $submission->capacity?->effective_minutes ?? 0;
        $requiredMinutes = (int) $submission->activities->sum('required_minutes');
        $utilization = $effectiveMinutes > 0 ? round(($requiredMinutes / $effectiveMinutes) * 100, 1) : null;

        $status = match (true) {
            $utilization === null => WorkloadStatus::Unavailable,
            $utilization < (float) $threshold->available_below => WorkloadStatus::Available,
            $utilization <= (float) $threshold->healthy_up_to => WorkloadStatus::Healthy,
            $utilization <= (float) $threshold->dense_up_to => WorkloadStatus::Dense,
            default => WorkloadStatus::Overload,
        };

        return [
            'effective_minutes' => $effectiveMinutes,
            'required_minutes' => $requiredMinutes,
            'utilization' => $utilization,
            'status' => $status,
            'by_type' => $submission->activities
                ->groupBy(fn ($activity) => $activity->work_type)
                ->map(fn ($activities) => (int) $activities->sum('required_minutes'))
                ->all(),
        ];
    }
}

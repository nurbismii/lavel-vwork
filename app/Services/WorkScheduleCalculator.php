<?php

namespace App\Services;

use Carbon\CarbonInterface;
use InvalidArgumentException;

class WorkScheduleCalculator
{
    public function workDays(
        CarbonInterface $start,
        CarbonInterface $end,
        CarbonInterface $anchor,
        int $cycleWorkDays,
        int $cycleOffDays,
    ): int {
        if ($end->isBefore($start)) {
            throw new InvalidArgumentException('Tanggal akhir periode tidak boleh sebelum tanggal awal.');
        }

        $cycleLength = $cycleWorkDays + $cycleOffDays;
        if ($cycleWorkDays < 1 || $cycleOffDays < 1 || $cycleLength > 366) {
            throw new InvalidArgumentException('Pola kerja tidak valid.');
        }

        $workDays = 0;
        $date = $start->toImmutable()->startOfDay();
        $lastDate = $end->toImmutable()->startOfDay();
        $anchorDate = $anchor->toImmutable()->startOfDay();

        while ($date->lessThanOrEqualTo($lastDate)) {
            $offset = $anchorDate->diffInDays($date, false);
            $position = (($offset % $cycleLength) + $cycleLength) % $cycleLength;

            if ($position < $cycleWorkDays) {
                $workDays++;
            }

            $date = $date->addDay();
        }

        return $workDays;
    }
}

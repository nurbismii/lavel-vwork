<?php

namespace Tests\Unit;

use App\Services\WorkScheduleCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WorkScheduleCalculatorTest extends TestCase
{
    #[DataProvider('monthlySchedules')]
    public function test_it_calculates_rotating_work_days(
        int $workDays,
        int $offDays,
        string $anchor,
        int $expected,
    ): void {
        $calculator = new WorkScheduleCalculator;

        $actual = $calculator->workDays(
            CarbonImmutable::parse('2026-08-01'),
            CarbonImmutable::parse('2026-08-31'),
            CarbonImmutable::parse($anchor),
            $workDays,
            $offDays,
        );

        $this->assertSame($expected, $actual);
    }

    public static function monthlySchedules(): array
    {
        return [
            '6:1 tujuh jam' => [6, 1, '2026-08-01', 27],
            '5:2 senin sampai jumat' => [5, 2, '2026-07-27', 21],
            '14:1 tujuh jam' => [14, 1, '2026-08-01', 29],
        ];
    }
}

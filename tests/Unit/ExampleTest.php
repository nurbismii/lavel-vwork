<?php

namespace Tests\Unit;

use App\Services\WorkloadCalculator;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function test_capacity_and_activity_calculation_are_deterministic(): void
    {
        $calculator = new WorkloadCalculator;

        $this->assertSame(['gross_minutes' => 9600, 'effective_minutes' => 8160], $calculator->capacity(20, 8, 85));
        $this->assertSame(750, $calculator->requiredMinutes(50, 15));
    }
}

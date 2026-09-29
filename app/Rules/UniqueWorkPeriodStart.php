<?php

namespace App\Rules;

use App\Models\WorkPeriod;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueWorkPeriodStart implements ValidationRule
{
    public function __construct(private readonly ?WorkPeriod $ignoredPeriod = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return;
        }

        $exists = WorkPeriod::query()
            ->whereDate('period_start', $value)
            ->when($this->ignoredPeriod, fn ($query) => $query->whereKeyNot($this->ignoredPeriod->getKey()))
            ->exists();

        if ($exists) {
            $fail('Periode untuk bulan tersebut sudah tersedia.');
        }
    }
}

<?php

namespace App\Http\Requests;

use App\Models\WorkPeriod;
use App\Rules\UniqueWorkPeriodStart;
use Illuminate\Foundation\Http\FormRequest;

class WorkPeriodUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $period = $this->route('period');

        return [
            'period_start' => [
                'required',
                'date_format:Y-m-d',
                new UniqueWorkPeriodStart($period instanceof WorkPeriod ? $period : null),
            ],
            'submission_deadline' => ['required', 'date', 'after_or_equal:period_start'],
        ];
    }
}

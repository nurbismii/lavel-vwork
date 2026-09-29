<?php

namespace App\Http\Requests;

use App\Rules\UniqueWorkPeriodStart;
use Illuminate\Foundation\Http\FormRequest;

class WorkPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period_start' => ['required', 'date_format:Y-m-d', new UniqueWorkPeriodStart],
            'submission_deadline' => ['required', 'date', 'after_or_equal:period_start'],
            'standard_productive_percentage' => ['required', 'numeric', 'between:1,100'],
            'capacity_policy_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

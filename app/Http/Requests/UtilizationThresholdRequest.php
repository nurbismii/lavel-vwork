<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UtilizationThresholdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'available_below' => ['required', 'numeric', 'min:0', 'lt:healthy_up_to'],
            'healthy_up_to' => ['required', 'numeric', 'gt:available_below', 'lt:dense_up_to'],
            'dense_up_to' => ['required', 'numeric', 'gt:healthy_up_to', 'max:999.99'],
            'effective_from' => ['required', 'date', 'after_or_equal:today', Rule::unique('utilization_thresholds', 'effective_from')],
            'is_provisional' => ['required', 'boolean'],
            'change_reason' => ['required', 'string', 'max:2000'],
        ];
    }
}

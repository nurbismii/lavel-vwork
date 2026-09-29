<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CapacityStandardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'standard_productive_percentage' => ['required', 'numeric', 'between:1,100'],
            'capacity_policy_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'activity_date' => ['required', 'date', 'before_or_equal:today'],
            'category' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'work_type' => ['required', 'string', 'max:100'],
            'actual_volume' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'unit' => ['nullable', 'required_with:actual_volume', 'string', 'max:50'],
            'actual_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'exception_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

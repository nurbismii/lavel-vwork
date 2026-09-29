<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FollowUpActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'workload_submission_id' => ['nullable', 'exists:workload_submissions,id'],
            'organizational_unit_id' => ['required', 'exists:organizational_units,id'],
            'action_type' => ['required', Rule::in(['redistribution', 'process_improvement', 'automation', 'workforce_review'])],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'owner_id' => ['required', 'exists:users,id'],
            'target_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['open', 'in_progress', 'completed', 'cancelled'])],
        ];
    }
}

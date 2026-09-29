<?php

namespace App\Http\Requests;

use App\Enums\SubmissionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([SubmissionStatus::Approved->value, SubmissionStatus::RevisionRequired->value])],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

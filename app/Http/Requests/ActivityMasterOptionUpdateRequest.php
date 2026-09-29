<?php

namespace App\Http\Requests;

use App\Services\ActivityMasterOptionResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActivityMasterOptionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $resolver = app(ActivityMasterOptionResolver::class);
        $this->merge(['normalized_key' => $resolver->normalizedKey((string) $this->input('label'))]);
    }

    public function rules(): array
    {
        $option = $this->route('option');

        return [
            'label' => ['required', 'string', 'max:100'],
            'normalized_key' => [
                'required', 'string', 'max:100',
                Rule::unique('activity_master_options', 'normalized_key')
                    ->where(fn ($query) => $query->where('type', $option->type))
                    ->ignore($option->id),
            ],
        ];
    }
}

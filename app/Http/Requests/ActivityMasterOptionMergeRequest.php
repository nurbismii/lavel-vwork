<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActivityMasterOptionMergeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_id' => ['required', 'integer', 'exists:activity_master_options,id'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            if ((int) $this->input('target_id') === (int) $this->route('option')->id) {
                $validator->errors()->add('target_id', 'Opsi tujuan harus berbeda dari opsi sumber.');
            }
        }];
    }
}

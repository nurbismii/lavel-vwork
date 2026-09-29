<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserManagementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'employee_code' => ['nullable', 'string', 'max:50', Rule::unique('users', 'employee_code')->ignore($user)],
            'position' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'organizational_unit_id' => ['required', 'exists:organizational_units,id'],
            'supervisor_id' => ['nullable', 'exists:users,id', Rule::notIn([$user?->id])],
            'is_active' => ['required', 'boolean'],
            'cycle_work_days' => ['sometimes', 'required', 'integer', 'between:1,365'],
            'cycle_off_days' => ['sometimes', 'required', 'integer', 'between:1,365'],
            'daily_work_hours' => ['sometimes', 'required', 'numeric', 'between:0.25,24'],
            'work_cycle_anchor_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'password' => [$user ? 'nullable' : 'required', 'nullable', 'string', 'min:10', 'confirmed'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            $cycleLength = $this->integer('cycle_work_days') + $this->integer('cycle_off_days');
            if ($this->hasAny(['cycle_work_days', 'cycle_off_days']) && $cycleLength > 366) {
                $validator->errors()->add('cycle_work_days', 'Total panjang siklus kerja dan off maksimal 366 hari.');
            }
        }];
    }
}

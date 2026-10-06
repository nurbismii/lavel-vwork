<?php

namespace App\Http\Requests;

use App\Enums\ProgressStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WorkProgressItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entry_mode' => ['required', Rule::in(['actual', 'planned'])],
            'source_activity_id' => ['nullable', 'integer', 'exists:workload_activities,id'],
            'report_date' => ['required', 'date', 'before_or_equal:today'],
            'category' => ['nullable', 'required_without:source_activity_id', 'string', 'max:100'],
            'name' => ['nullable', 'required_without:source_activity_id', 'string', 'max:255'],
            'status' => ['required', Rule::enum(ProgressStatus::class)],
            'progress_summary' => ['required', 'string', 'max:3000'],
            'obstacle_note' => ['nullable', 'string', 'max:3000'],
            'action_note' => ['required', 'string', 'max:3000'],
            'start_date' => ['nullable', 'date'],
            'target_date' => ['nullable', 'required_unless:status,completed', 'date', Rule::when($this->filled('start_date'), 'after_or_equal:start_date')],
            'progress_percentage' => ['required', 'integer', 'between:0,100'],
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.date' => 'Rencana mulai harus berupa tanggal yang valid.',
            'target_date.after_or_equal' => 'Target selesai tidak boleh sebelum rencana mulai.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $status = ProgressStatus::tryFrom($this->string('status')->toString());
                $percentage = $this->integer('progress_percentage');
                $hasSourceActivity = $this->filled('source_activity_id');
                $entryMode = $this->string('entry_mode')->toString();

                if ($entryMode === 'actual' && ! $hasSourceActivity) {
                    $validator->errors()->add('source_activity_id', 'Pilih pekerjaan yang berasal dari aktivitas aktual.');
                }

                if ($entryMode === 'planned' && $hasSourceActivity) {
                    $validator->errors()->add('source_activity_id', 'Rencana pekerjaan tidak boleh ditautkan ke aktivitas aktual.');
                }

                if ($hasSourceActivity && $status === ProgressStatus::Planned) {
                    $validator->errors()->add('status', 'Aktivitas aktual tidak dapat dicatat sebagai pekerjaan yang belum dimulai.');
                }

                if ($entryMode === 'planned' && $status !== null && $status !== ProgressStatus::Planned) {
                    $validator->errors()->add('source_activity_id', 'Pekerjaan selesai atau sedang dikerjakan harus dipilih dari aktivitas aktual.');
                }

                if ($status === ProgressStatus::Completed && $percentage !== 100) {
                    $validator->errors()->add('progress_percentage', 'Pekerjaan selesai harus memiliki progres 100%.');
                }

                if ($status === ProgressStatus::InProgress && ($percentage < 1 || $percentage > 99)) {
                    $validator->errors()->add('progress_percentage', 'Pekerjaan yang sedang dikerjakan harus memiliki progres antara 1% dan 99%.');
                }

                if ($status === ProgressStatus::Planned && $percentage !== 0) {
                    $validator->errors()->add('progress_percentage', 'Pekerjaan yang akan dikerjakan harus memiliki progres 0%.');
                }
            },
        ];
    }
}

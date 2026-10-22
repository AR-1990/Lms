<?php

namespace App\Http\Requests\Admissions;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveAdmissionTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-admissions');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scheduled_at' => ['required', 'date'],
            'venue' => ['nullable', 'string', 'max:255'],
            'subject_id' => ['nullable', 'integer', Rule::exists('subjects', 'id')->whereNull('deleted_at')],
            'max_marks' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'obtained_marks' => ['nullable', 'numeric', 'min:0'],
            'result' => ['nullable', 'string', Rule::in(['pass', 'fail', 'absent'])],
            'examiner_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $max = $this->input('max_marks', $this->route('admission_test')?->max_marks);

            if ($this->filled('obtained_marks') && $max !== null && (float) $this->input('obtained_marks') > (float) $max) {
                $validator->errors()->add('obtained_marks', "Marks cannot be more than {$max}.");
            }
        });
    }
}

<?php

namespace App\Http\Requests\Academics;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveCurriculumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-academics');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'academic_session_id' => ['required', 'integer', Rule::exists('academic_sessions', 'id')->whereNull('deleted_at')],
            'class_id' => ['required', 'integer', Rule::exists('classes', 'id')->whereNull('deleted_at')],
            'subject_id' => [
                'required', 'integer',
                Rule::exists('subjects', 'id')->where('is_active', 1)->whereNull('deleted_at'),
                Rule::unique('class_subject', 'subject_id')
                    ->where('academic_session_id', $this->input('academic_session_id'))
                    ->where('class_id', $this->input('class_id'))
                    ->whereNull('deleted_at')
                    ->ignore($this->route('curriculum')),
            ],
            'is_optional' => ['sometimes', 'boolean'],
            'periods_per_week' => ['nullable', 'integer', 'min:0', 'max:60'],
            'theory_max_marks' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'practical_max_marks' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'pass_marks' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:32000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $total = (int) $this->input('theory_max_marks') + (int) $this->input('practical_max_marks');

            if ($this->filled('pass_marks') && (int) $this->input('pass_marks') > $total) {
                $validator->errors()->add('pass_marks', 'Pass marks cannot be more than the total marks ('.$total.').');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject_id.unique' => 'This subject is already in the class curriculum for this session.',
            'subject_id.exists' => 'Choose an active subject.',
        ];
    }
}

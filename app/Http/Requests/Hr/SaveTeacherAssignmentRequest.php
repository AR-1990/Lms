<?php

namespace App\Http\Requests\Hr;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTeacherAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-hr');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('is_teaching_staff', 1)->whereNull('deleted_at')],
            'academic_session_id' => ['required', 'integer', Rule::exists('academic_sessions', 'id')->whereNull('deleted_at')],
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')->whereNull('deleted_at')],
            'subject_id' => [
                'required', 'integer', Rule::exists('subjects', 'id')->whereNull('deleted_at'),
                Rule::unique('teacher_assignments', 'subject_id')
                    ->where('academic_session_id', $this->input('academic_session_id'))
                    ->where('section_id', $this->input('section_id'))
                    ->whereNull('deleted_at')
                    ->ignore($this->route('teacher_assignment')),
            ],
            'is_class_teacher' => ['sometimes', 'boolean'],
            'periods_per_week' => ['nullable', 'integer', 'min:0', 'max:60'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_id.exists' => 'Choose a teaching staff member.',
            'subject_id.unique' => 'This subject already has a teacher in this section.',
        ];
    }
}

<?php

namespace App\Http\Requests\Exams;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMarksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission(['submit-grades', 'manage-exams']);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'exam_subject_id' => ['required', 'integer', Rule::exists('exam_subjects', 'id')->whereNull('deleted_at')],
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')->whereNull('deleted_at')],
            'submit' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'rows' => ['required', 'array', 'min:1', 'max:200'],
            'rows.*.enrollment_id' => ['required', 'integer', 'distinct'],
            'rows.*.theory_marks' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'rows.*.practical_marks' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'rows.*.is_absent' => ['sometimes', 'boolean'],
            'rows.*.teacher_remarks' => ['nullable', 'string', 'max:255'],
        ];
    }
}

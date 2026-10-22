<?php

namespace App\Http\Requests\Exams;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-exams');
    }

    /**
     * The exam fee is in paisa; one exam per class per term.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'exam_term_id' => ['required', 'integer', Rule::exists('exam_terms', 'id')->whereNull('deleted_at')],
            'class_id' => [
                'required', 'integer', Rule::exists('classes', 'id')->whereNull('deleted_at'),
                Rule::unique('exams', 'class_id')->where('exam_term_id', $this->input('exam_term_id'))->whereNull('deleted_at')->ignore($this->route('exam')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'exam_type_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'exam_type')->whereNull('deleted_at')],
            'grading_scale_id' => ['nullable', 'integer', Rule::exists('grading_scales', 'id')->whereNull('deleted_at')],
            'fee_head_id' => ['nullable', 'required_with:exam_fee_amount', 'integer', Rule::exists('fee_heads', 'id')->where('is_active', 1)->whereNull('deleted_at')],
            'exam_fee_amount' => ['nullable', 'integer', 'min:1'],
            'fee_due_date' => ['nullable', 'date_format:Y-m-d'],
            'require_fee_clearance' => ['sometimes', 'boolean'],
            'min_attendance_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'instructions' => ['nullable', 'string', 'max:3000'],
            'marks_entry_deadline' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['class_id.unique' => 'This class already has an exam in this term.'];
    }
}

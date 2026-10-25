<?php

namespace App\Http\Requests\Exams;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An online MCQ test and (optionally) its full list of questions, each with 2–6 options.
 */
class SaveOnlineTestRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:150'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')->whereNull('deleted_at')],
            'subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')->whereNull('deleted_at')],
            'exam_subject_id' => ['nullable', 'integer', Rule::exists('exam_subjects', 'id')->whereNull('deleted_at')],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after:opens_at'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:300'],
            'shuffle_questions' => ['sometimes', 'boolean'],
            'show_answers' => ['sometimes', 'boolean'],
            'questions' => ['sometimes', 'array', 'min:1', 'max:200'],
            'questions.*.question' => ['required', 'string', 'max:2000'],
            'questions.*.marks' => ['nullable', 'integer', 'min:1', 'max:100'],
            'questions.*.options' => ['required', 'array', 'min:2', 'max:6'],
            'questions.*.options.*.option_text' => ['required', 'string', 'max:500'],
            'questions.*.options.*.is_correct' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['questions.*.question' => 'question text', 'questions.*.options' => 'options', 'questions.*.options.*.option_text' => 'option text'];
    }
}

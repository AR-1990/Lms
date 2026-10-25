<?php

namespace App\Http\Requests\Students;

use App\Services\PromotionService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromoteStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-students');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from_session_id' => ['required', 'integer', Rule::exists('academic_sessions', 'id')->whereNull('deleted_at')],
            'to_session_id' => ['required', 'integer', Rule::exists('academic_sessions', 'id')->whereNull('deleted_at')],
            'class_id' => ['required', 'integer', Rule::exists('classes', 'id')->whereNull('deleted_at')],
            'students' => ['required', 'array', 'min:1', 'max:500'],
            'students.*.enrollment_id' => ['required', 'integer', 'distinct'],
            'students.*.action' => ['required', 'string', Rule::in(PromotionService::ACTIONS)],
            'students.*.section_id' => ['nullable', 'integer'],
            'carry_concessions' => ['sometimes', 'boolean'],
            'carry_transport' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['students.required' => 'Tick at least one student to move.'];
    }
}

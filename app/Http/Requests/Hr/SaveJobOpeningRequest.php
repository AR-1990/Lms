<?php

namespace App\Http\Requests\Hr;

use App\Models\JobOpening;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveJobOpeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-recruitment');
    }

    /**
     * Salaries are in paisa.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'designation_id' => ['required', 'integer', Rule::exists('designations', 'id')->whereNull('deleted_at')],
            'employment_type_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'employment_type')->whereNull('deleted_at')],
            'positions' => ['nullable', 'integer', 'min:1', 'max:500'],
            'subjects_note' => ['nullable', 'string', 'max:255'],
            'min_qualification_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'qualification_level')->whereNull('deleted_at')],
            'min_experience_years' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'salary_min' => ['nullable', 'integer', 'min:0'],
            'salary_max' => ['nullable', 'integer', 'min:0', 'gte:salary_min'],
            'description' => ['nullable', 'string', 'max:5000'],
            'requirements' => ['nullable', 'string', 'max:5000'],
            'opens_on' => ['nullable', 'date_format:Y-m-d'],
            'closes_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:opens_on'],
            'is_published' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', Rule::in(JobOpening::STATUSES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['salary_max.gte' => 'The maximum salary cannot be less than the minimum.'];
    }
}

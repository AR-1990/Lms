<?php

namespace App\Http\Requests\Hr;

use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HireCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-recruitment') && $this->user()->hasPermission('manage-hr');
    }

    /**
     * Anything not given is taken from the application and its job opening.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'joining_date' => ['required', 'date_format:Y-m-d'],
            'gender_id' => [$this->route('job_application')?->gender_id ? 'nullable' : 'required', 'integer', Rule::exists('lookups', 'id')->where('type', 'gender')->whereNull('deleted_at')],
            'designation_id' => ['nullable', 'integer', Rule::exists('designations', 'id')->whereNull('deleted_at')],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'employment_type_id' => [$this->route('job_application')?->jobOpening?->employment_type_id ? 'nullable' : 'required', 'integer', Rule::exists('lookups', 'id')->where('type', 'employment_type')->whereNull('deleted_at')],
            'probation_end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:joining_date'],
            'portal_role' => ['nullable', 'string', Rule::in(Role::whereNotIn('slug', ['admin', 'student', 'parent'])->pluck('slug')->all())],
            'portal_password' => ['required_with:portal_role', 'nullable', 'string', 'min:8'],
        ];
    }
}

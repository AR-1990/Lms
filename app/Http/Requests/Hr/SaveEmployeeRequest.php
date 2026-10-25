<?php

namespace App\Http\Requests\Hr;

use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-hr');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('cnic'))) {
            $this->merge(['cnic' => trim($this->input('cnic')) ?: null]);
        }
    }

    /**
     * A portal login can be created together with a new employee (portal_role + portal_password).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $employee = $this->route('employee');
        $lookup = fn (string $type): array => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', $type)->whereNull('deleted_at')];

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'father_or_spouse_name' => ['nullable', 'string', 'max:255'],
            'gender_id' => ['required', 'integer', Rule::exists('lookups', 'id')->where('type', 'gender')->whereNull('deleted_at')],
            'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'cnic' => ['nullable', 'string', 'regex:/^\d{5}-\d{7}-\d$/', Rule::unique('employees', 'cnic')->ignore($employee)->whereNull('deleted_at')],
            'cnic_expiry' => ['nullable', 'date_format:Y-m-d'],
            'marital_status_id' => $lookup('marital_status'),
            'religion_id' => $lookup('religion'),
            'blood_group_id' => $lookup('blood_group'),
            'nationality_id' => $lookup('nationality'),
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 -]{6,28}$/'],
            'alt_phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('employees', 'email')->ignore($employee)->whereNull('deleted_at')],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'current_address' => ['nullable', 'string', 'max:1000'],
            'permanent_address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'designation_id' => ['required', 'integer', Rule::exists('designations', 'id')->whereNull('deleted_at')],
            'work_shift_id' => ['nullable', 'integer', Rule::exists('work_shifts', 'id')->whereNull('deleted_at')],
            'employment_type_id' => ['required', 'integer', Rule::exists('lookups', 'id')->where('type', 'employment_type')->whereNull('deleted_at')],
            'reports_to_id' => ['nullable', 'integer', Rule::notIn(array_filter([$employee?->id])), Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'joining_date' => ['required', 'date_format:Y-m-d'],
            'probation_end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:joining_date'],
            'confirmation_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:joining_date'],
            'contract_end_date' => ['nullable', 'date_format:Y-m-d', 'after:joining_date'],
            'is_teaching_staff' => ['sometimes', 'boolean'],
            'ntn' => ['nullable', 'string', 'max:30'],
            'eobi_no' => ['nullable', 'string', 'max:30'],
            'bank_id' => $lookup('bank'),
            'bank_account_title' => ['nullable', 'string', 'max:255'],
            'bank_account_no' => ['nullable', 'string', 'max:50'],
            'iban' => ['nullable', 'string', 'max:34'],
            'portal_role' => [$employee ? 'prohibited' : 'nullable', 'string', Rule::in(Role::whereNotIn('slug', ['admin', 'student', 'parent'])->pluck('slug')->all())],
            'portal_password' => ['required_with:portal_role', 'nullable', 'string', 'min:8'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cnic.regex' => 'Enter the CNIC as 35202-1234567-1.',
            'phone.regex' => 'Enter a valid phone number, e.g. 03001234567.',
            'reports_to_id.not_in' => 'An employee cannot report to themself.',
            'portal_role.in' => 'Choose a staff role for the portal login.',
            'portal_role.prohibited' => 'Create the portal login from the employee profile instead.',
        ];
    }
}

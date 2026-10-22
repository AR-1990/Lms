<?php

namespace App\Http\Requests\Hr;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveJobApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-recruitment');
    }

    /**
     * Salaries are in paisa. The opening cannot change after the application is received.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $lookup = fn (string $type): array => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', $type)->whereNull('deleted_at')];

        return [
            'job_opening_id' => [$this->route('job_application') ? 'prohibited' : 'required', 'integer', Rule::exists('job_openings', 'id')->whereNull('deleted_at')],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'cnic' => ['nullable', 'string', 'regex:/^\d{5}-\d{7}-\d$/'],
            'gender_id' => $lookup('gender'),
            'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 -]{6,28}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'highest_qualification_id' => $lookup('qualification_level'),
            'qualification_details' => ['nullable', 'string', 'max:2000'],
            'experience_years' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'current_employer' => ['nullable', 'string', 'max:255'],
            'current_salary' => ['nullable', 'integer', 'min:0'],
            'expected_salary' => ['nullable', 'integer', 'min:0'],
            'notice_period_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'source_id' => $lookup('job_source'),
            'referred_by_employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'remarks' => ['nullable', 'string', 'max:2000'],
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
        ];
    }
}

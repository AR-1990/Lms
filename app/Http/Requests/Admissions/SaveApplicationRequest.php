<?php

namespace App\Http\Requests\Admissions;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Used to submit an application, to convert an inquiry, and to update an open application
 * (session and class are fixed once submitted).
 */
class SaveApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-admissions');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isUpdate = $this->route('admission_application') !== null;
        $lookup = fn (string $type, bool $required = false): array => [$required ? 'required' : 'nullable', 'integer', Rule::exists('lookups', 'id')->where('type', $type)->whereNull('deleted_at')];

        return [
            'academic_session_id' => [$isUpdate ? 'prohibited' : 'required', 'integer', Rule::exists('academic_sessions', 'id')->whereNull('deleted_at')],
            'class_applied_id' => [$isUpdate ? 'prohibited' : 'required', 'integer', Rule::exists('classes', 'id')->where('is_active', 1)->whereNull('deleted_at')],
            'applied_on' => ['nullable', 'date_format:Y-m-d'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'gender_id' => $lookup('gender', true),
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before:today'],
            'place_of_birth' => ['nullable', 'string', 'max:255'],
            'b_form_no' => ['nullable', 'string', 'regex:/^\d{5}-\d{7}-\d$/'],
            'nationality_id' => $lookup('nationality'),
            'religion_id' => $lookup('religion'),
            'blood_group_id' => $lookup('blood_group'),
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'previous_school' => ['nullable', 'string', 'max:255'],
            'previous_class' => ['nullable', 'string', 'max:50'],
            'previous_result' => ['nullable', 'string', 'max:50'],
            'reason_for_leaving' => ['nullable', 'string', 'max:2000'],
            'has_sibling_in_school' => ['sometimes', 'boolean'],
            'sibling_student_id' => ['nullable', 'integer', Rule::exists('students', 'id')->whereNull('deleted_at')],
            'special_needs' => ['nullable', 'string', 'max:2000'],
            'medical_notes' => ['nullable', 'string', 'max:2000'],
            'transport_required' => ['sometimes', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'guardians' => [$isUpdate ? 'sometimes' : 'required', 'array', 'min:1', 'max:4'],
            'guardians.*.relation_id' => $lookup('guardian_relation', true),
            'guardians.*.name' => ['required', 'string', 'max:255'],
            'guardians.*.cnic' => ['nullable', 'string', 'regex:/^\d{5}-\d{7}-\d$/'],
            'guardians.*.phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 -]{6,28}$/'],
            'guardians.*.email' => ['nullable', 'email', 'max:255'],
            'guardians.*.occupation_id' => $lookup('occupation'),
            'guardians.*.employer' => ['nullable', 'string', 'max:255'],
            'guardians.*.monthly_income' => ['nullable', 'integer', 'min:0'],
            'guardians.*.is_primary_contact' => ['sometimes', 'boolean'],
            'guardians.*.existing_guardian_id' => ['nullable', 'integer', Rule::exists('guardians', 'id')->whereNull('deleted_at')],
            'guardians.0.phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 -]{6,28}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'b_form_no.regex' => 'Enter the B-Form number as 35202-1234567-1.',
            'guardians.*.cnic.regex' => 'Enter the CNIC as 35202-1234567-1.',
            'guardians.0.phone.required' => 'The first guardian needs a phone number for school messages.',
            'class_applied_id.exists' => 'Choose an active class.',
        ];
    }
}

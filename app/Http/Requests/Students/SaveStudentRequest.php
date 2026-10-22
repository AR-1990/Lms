<?php

namespace App\Http\Requests\Students;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Direct student entry (e.g. moving existing students into the system). On create it also takes
 * the enrolment and at least one guardian; on update only the student's own fields.
 */
class SaveStudentRequest extends FormRequest
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
        $student = $this->route('student');
        $creating = $student === null;
        $lookup = fn (string $type, bool $required = false): array => [$required ? 'required' : 'nullable', 'integer', Rule::exists('lookups', 'id')->where('type', $type)->whereNull('deleted_at')];

        $rules = [
            'admission_no' => [$creating ? 'nullable' : 'prohibited', 'string', 'max:30', Rule::unique('students', 'admission_no')],
            'admission_date' => [$creating ? 'required' : 'sometimes', 'date_format:Y-m-d'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'gender_id' => $lookup('gender', true),
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before:today'],
            'place_of_birth' => ['nullable', 'string', 'max:255'],
            'b_form_no' => ['nullable', 'string', 'regex:/^\d{5}-\d{7}-\d$/', Rule::unique('students', 'b_form_no')->ignore($student)->whereNull('deleted_at')],
            'nationality_id' => $lookup('nationality'),
            'religion_id' => $lookup('religion'),
            'blood_group_id' => $lookup('blood_group'),
            'mother_tongue' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'permanent_address' => ['nullable', 'string', 'max:1000'],
            'house_id' => $lookup('house'),
            'previous_school' => ['nullable', 'string', 'max:255'],
            'previous_class' => ['nullable', 'string', 'max:50'],
            'is_staff_child' => ['sometimes', 'boolean'],
            'staff_parent_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'special_needs' => ['nullable', 'string', 'max:2000'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];

        if (! $creating) {
            return $rules;
        }

        return [
            ...$rules,
            'enrollment' => ['required', 'array'],
            'enrollment.academic_session_id' => ['required', 'integer', Rule::exists('academic_sessions', 'id')->whereNull('deleted_at')],
            'enrollment.class_id' => ['required', 'integer', Rule::exists('classes', 'id')->whereNull('deleted_at')],
            'enrollment.section_id' => ['nullable', 'integer', Rule::exists('sections', 'id')->whereNull('deleted_at')],
            'enrollment.roll_no' => ['nullable', 'string', 'max:20'],
            'guardians' => ['required', 'array', 'min:1', 'max:4'],
            'guardians.*.guardian_id' => ['nullable', 'integer', Rule::exists('guardians', 'id')->whereNull('deleted_at')],
            'guardians.*.relation_id' => $lookup('guardian_relation', true),
            'guardians.*.name' => ['required_without:guardians.*.guardian_id', 'nullable', 'string', 'max:255'],
            'guardians.*.phone' => ['required_without:guardians.*.guardian_id', 'nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 -]{6,28}$/'],
            'guardians.*.cnic' => ['nullable', 'string', 'regex:/^\d{5}-\d{7}-\d$/'],
            'guardians.*.email' => ['nullable', 'email', 'max:255'],
            'guardians.*.occupation_id' => $lookup('occupation'),
            'guardians.*.is_primary_contact' => ['sometimes', 'boolean'],
            'guardians.*.is_fee_payer' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'b_form_no.regex' => 'Enter the B-Form number as 35202-1234567-1.',
            'b_form_no.unique' => 'Another student already has this B-Form number.',
            'guardians.*.cnic.regex' => 'Enter the CNIC as 35202-1234567-1.',
        ];
    }
}

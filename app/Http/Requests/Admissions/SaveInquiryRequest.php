<?php

namespace App\Http\Requests\Admissions;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-admissions');
    }

    /**
     * `converted` is set only by converting the inquiry into an application.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'inquiry_date' => ['nullable', 'date_format:Y-m-d'],
            'source_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'inquiry_source')->whereNull('deleted_at')],
            'parent_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 -]{6,28}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'child_name' => ['nullable', 'string', 'max:255'],
            'child_dob' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'class_sought_id' => ['nullable', 'integer', Rule::exists('classes', 'id')->whereNull('deleted_at')],
            'academic_session_id' => ['nullable', 'integer', Rule::exists('academic_sessions', 'id')->whereNull('deleted_at')],
            'previous_school' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:3000'],
            'status' => ['sometimes', 'string', Rule::in(['new', 'contacted', 'visit_scheduled', 'closed'])],
            'follow_up_on' => ['nullable', 'date_format:Y-m-d'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'closed_reason_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'inquiry_status_reason')->whereNull('deleted_at')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['phone.regex' => 'Enter a valid phone number, e.g. 03001234567.'];
    }
}

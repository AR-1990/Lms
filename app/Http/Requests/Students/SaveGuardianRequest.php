<?php

namespace App\Http\Requests\Students;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Link a guardian to a student (new or existing) or update a guardian's details.
 */
class SaveGuardianRequest extends FormRequest
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
        $linking = $this->route('guardian') === null;
        $lookup = fn (string $type): array => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', $type)->whereNull('deleted_at')];

        return [
            'guardian_id' => [$linking ? 'nullable' : 'prohibited', 'integer', Rule::exists('guardians', 'id')->whereNull('deleted_at')],
            'relation_id' => [$linking ? 'required' : 'prohibited', 'integer', Rule::exists('lookups', 'id')->where('type', 'guardian_relation')->whereNull('deleted_at')],
            'is_primary_contact' => ['sometimes', 'boolean'],
            'is_fee_payer' => ['sometimes', 'boolean'],
            'can_pick_up' => ['sometimes', 'boolean'],
            'lives_with_student' => ['sometimes', 'boolean'],
            'name' => [$linking ? 'required_without:guardian_id' : 'required', 'nullable', 'string', 'max:255'],
            'cnic' => ['nullable', 'string', 'regex:/^\d{5}-\d{7}-\d$/', Rule::unique('guardians', 'cnic')->ignore($this->route('guardian'))->whereNull('deleted_at')],
            'phone' => [$linking ? 'required_without:guardian_id' : 'required', 'nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 -]{6,28}$/'],
            'alt_phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'occupation_id' => $lookup('occupation'),
            'employer' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'monthly_income' => ['nullable', 'integer', 'min:0'],
            'education_id' => $lookup('qualification_level'),
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cnic.regex' => 'Enter the CNIC as 35202-1234567-1.',
            'cnic.unique' => 'A guardian with this CNIC already exists — link that guardian instead.',
        ];
    }
}

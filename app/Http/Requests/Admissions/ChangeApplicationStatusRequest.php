<?php

namespace App\Http\Requests\Admissions;

use App\Models\AdmissionApplication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeApplicationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-admissions');
    }

    /**
     * `enrolled` is reached only through the enrol action.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(array_diff(array_keys(AdmissionApplication::TRANSITIONS), ['submitted', 'enrolled']))],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'rejection_reason_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'admission_rejection_reason')->whereNull('deleted_at')],
            'offer_expires_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['status.in' => 'Choose a valid next step. Use Enrol to admit an accepted child.'];
    }
}

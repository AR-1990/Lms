<?php

namespace App\Http\Requests\Hr;

use App\Models\JobApplication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeJobApplicationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-recruitment');
    }

    /**
     * `hired` is reached only through the hire action.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(array_diff(array_keys(JobApplication::TRANSITIONS), ['received', 'hired']))],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'rejection_reason_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->whereNull('deleted_at')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['status.in' => 'Choose a valid next step. Use the Hire action to hire a candidate.'];
    }
}

<?php

namespace App\Http\Requests\Hr;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveLeaveTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-leave');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:20'],
            'days_per_year' => ['required', 'numeric', 'min:0', 'max:366'],
            'is_paid' => ['sometimes', 'boolean'],
            'carry_forward' => ['sometimes', 'boolean'],
            'max_carry_forward' => ['nullable', 'numeric', 'min:0', 'max:366'],
            'requires_document' => ['sometimes', 'boolean'],
            'applicable_gender_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'gender')->whereNull('deleted_at')],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:32000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

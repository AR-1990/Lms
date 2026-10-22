<?php

namespace App\Http\Requests\Fees;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveConcessionTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-fee-setup');
    }

    /**
     * `default_value` is percent × 100 (25% = 2500) or paisa for fixed concessions.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('concession_types', 'name')->ignore($this->route('concession_type'))->whereNull('deleted_at')],
            'calculation' => ['required', 'string', Rule::in(['percent', 'fixed'])],
            'default_value' => ['required', 'integer', 'min:0'],
            'expense_account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where('is_group', 0)->whereNull('deleted_at')],
            'requires_approval' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('calculation') === 'percent' && (int) $this->input('default_value') > 10000) {
                $validator->errors()->add('default_value', 'A percentage cannot be more than 100%.');
            }
        });
    }
}

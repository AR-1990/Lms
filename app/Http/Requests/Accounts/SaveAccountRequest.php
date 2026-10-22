<?php

namespace App\Http\Requests\Accounts;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-accounts');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    /**
     * Amounts are in paisa (Rs 1,500.50 = 150050).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'account_type_id' => ['required', 'integer', 'exists:account_types,id'],
            'parent_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->whereNull('deleted_at')],
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\-.]+$/', Rule::unique('accounts', 'code')->ignore($this->route('account'))->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255'],
            'is_group' => ['sometimes', 'boolean'],
            'is_bank_account' => ['sometimes', 'boolean'],
            'is_cash_account' => ['sometimes', 'boolean'],
            'opening_balance' => ['nullable', 'integer', 'min:0', 'max:999999999999999'],
            'opening_balance_type' => ['nullable', 'string', Rule::in(['debit', 'credit'])],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ((int) $this->input('opening_balance') > 0 && ! $this->filled('opening_balance_type')) {
                $validator->errors()->add('opening_balance_type', 'Say whether the opening balance is a debit or a credit.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'Another account already uses this code.',
            'code.regex' => 'Use letters, numbers, dots and dashes only.',
        ];
    }
}

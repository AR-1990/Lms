<?php

namespace App\Http\Requests\Accounts;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-accounts');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('iban'))) {
            $this->merge(['iban' => strtoupper(str_replace(' ', '', $this->input('iban'))) ?: null]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'account_id' => [
                'required', 'integer',
                Rule::exists('accounts', 'id')->where('is_bank_account', 1)->where('is_group', 0)->whereNull('deleted_at'),
                Rule::unique('bank_accounts', 'account_id')->ignore($this->route('bank_account'))->whereNull('deleted_at'),
            ],
            'bank_id' => ['required', 'integer', Rule::exists('lookups', 'id')->where('type', 'bank')->whereNull('deleted_at')],
            'branch' => ['nullable', 'string', 'max:255'],
            'account_title' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:50'],
            'iban' => ['nullable', 'string', 'max:34', 'regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9]{8,30}$/'],
            'branch_code' => ['nullable', 'string', 'max:20'],
            'is_default_for_fees' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_id.exists' => 'Choose a ledger account marked as a bank account.',
            'account_id.unique' => 'This ledger account is already linked to another bank account.',
            'iban.regex' => 'Enter a valid IBAN, e.g. PK36SCBL0000001123456702.',
        ];
    }
}

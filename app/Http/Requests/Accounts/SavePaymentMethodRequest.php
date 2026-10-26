<?php

namespace App\Http\Requests\Accounts;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePaymentMethodRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\-]+$/', Rule::unique('payment_methods', 'code')->ignore($this->route('payment_method'))->whereNull('deleted_at')],
            'account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('is_group', 0)->where('is_active', 1)->whereNull('deleted_at')],
            'requires_reference' => ['sometimes', 'boolean'],
            'requires_bank_details' => ['sometimes', 'boolean'],
            'is_online' => ['sometimes', 'boolean'],
            'payment_instructions' => ['nullable', 'string', 'max:1000'],
            'charges_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:32000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_id.exists' => 'Choose an active cash or bank account (not a group heading).',
        ];
    }
}

<?php

namespace App\Http\Requests\Accounts;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveVoucherTypeRequest extends FormRequest
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
     * The code is chosen once, when the type is created; it names the voucher number series.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => $this->route('voucher_type')
                ? ['prohibited']
                : ['required', 'string', 'min:2', 'max:10', 'regex:/^[A-Z][A-Z0-9]*$/', Rule::unique('voucher_types', 'code')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.prohibited' => 'The code of an existing voucher type cannot change.',
            'code.regex' => 'Use capital letters and numbers, starting with a letter (e.g. BRV).',
        ];
    }
}

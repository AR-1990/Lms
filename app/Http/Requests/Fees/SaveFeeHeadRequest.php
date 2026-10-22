<?php

namespace App\Http\Requests\Fees;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveFeeHeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-fee-setup');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code'))) ?: null]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $headId = $this->route('fee_head')?->id;

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('fee_heads', 'name')->ignore($headId)->whereNull('deleted_at')],
            'code' => ['nullable', 'string', 'max:20', 'regex:/^[A-Z0-9\-]+$/', Rule::unique('fee_heads', 'code')->ignore($headId)->whereNull('deleted_at')],
            'income_account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('is_group', 0)->where('is_active', 1)->whereNull('deleted_at')],
            'frequency_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'fee_frequency')->whereNull('deleted_at')],
            'is_refundable' => ['sometimes', 'boolean'],
            'is_concession_allowed' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:32000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['income_account_id.exists' => 'Choose an active income account (not a group heading).'];
    }
}

<?php

namespace App\Http\Requests\Accounts;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-vouchers');
    }

    /**
     * Amounts are in paisa. Balancing, group accounts and the fiscal year are checked by JournalService.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'voucher_type_id' => [$this->route('voucher') ? 'prohibited' : 'required', 'integer', Rule::exists('voucher_types', 'id')->whereNull('deleted_at')],
            'entry_date' => ['required', 'date_format:Y-m-d'],
            'narration' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:2', 'max:200'],
            'lines.*.account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->whereNull('deleted_at')],
            'lines.*.debit' => ['nullable', 'integer', 'min:0', 'max:9999999999999'],
            'lines.*.credit' => ['nullable', 'integer', 'min:0', 'max:9999999999999'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'voucher_type_id.prohibited' => 'The voucher type cannot change after the voucher is created.',
            'lines.min' => 'A voucher needs at least two lines.',
        ];
    }
}

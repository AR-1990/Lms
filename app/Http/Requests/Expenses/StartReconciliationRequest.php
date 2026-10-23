<?php

namespace App\Http\Requests\Expenses;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A bank statement to reconcile: date and closing balance (paisa, may be negative for an overdraft).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bank_account_id' => ['required', 'integer', Rule::exists('bank_accounts', 'id')->whereNull('deleted_at')],
            'statement_date' => ['required', 'date', 'before_or_equal:today'],
            'statement_balance' => ['required', 'integer', 'min:-100000000000', 'max:100000000000'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

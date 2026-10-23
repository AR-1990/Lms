<?php

namespace App\Http\Requests\Expenses;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A draft expense (bill). Money in paisa.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'expense_category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')->whereNull('deleted_at')],
            'vendor_id' => ['nullable', 'integer', Rule::exists('vendors', 'id')->whereNull('deleted_at')],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'tax_amount' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'payment_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')->whereNull('deleted_at')],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

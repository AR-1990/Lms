<?php

namespace App\Http\Requests\Expenses;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Paying an approved expense.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->whereNull('deleted_at')],
            'reference_no' => ['nullable', 'string', 'max:100'],
        ];
    }
}

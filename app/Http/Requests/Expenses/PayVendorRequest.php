<?php

namespace App\Http\Requests\Expenses;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A payment to a vendor against what they are owed.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vendor_id' => ['required', 'integer', Rule::exists('vendors', 'id')->whereNull('deleted_at')],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->whereNull('deleted_at')],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

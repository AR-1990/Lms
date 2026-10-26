<?php

namespace App\Http\Requests\Fees;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A family's online payment with its screenshot (amount in paisa; image or PDF up to 5 MB).
 */
class SubmitPaymentProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fee_invoice_id' => ['nullable', 'integer'],
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->whereNull('deleted_at')],
            'amount' => ['required', 'integer', 'min:100', 'max:100000000'],
            'paid_on' => ['required', 'date', 'before_or_equal:today', 'after:-60 days'],
            'reference_no' => ['required', 'string', 'max:100'],
            'payer_name' => ['nullable', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:500'],
            'screenshot' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['screenshot.required' => 'Attach the screenshot of the payment.', 'reference_no.required' => 'Enter the transaction ID from the screenshot.'];
    }
}

<?php

namespace App\Http\Requests\Fees;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceivePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-fees');
    }

    /**
     * Pay for a student, or for an applicant before enrolment. Amounts are in paisa.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required_without:admission_application_id', 'nullable', 'integer', Rule::exists('students', 'id')->whereNull('deleted_at')],
            'admission_application_id' => ['required_without:student_id', 'prohibits:student_id', 'nullable', 'integer', Rule::exists('admission_applications', 'id')->whereNull('deleted_at')],
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->whereNull('deleted_at')],
            'amount' => ['required', 'integer', 'min:1', 'max:9999999999'],
            'received_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'cheque_bank_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'bank')->whereNull('deleted_at')],
            'cheque_date' => ['nullable', 'date_format:Y-m-d'],
            'paid_by_guardian_id' => ['nullable', 'integer', Rule::exists('guardians', 'id')->whereNull('deleted_at')],
            'invoice_ids' => ['nullable', 'array', 'max:50'],
            'invoice_ids.*' => ['integer', 'distinct'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

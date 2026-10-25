<?php

namespace App\Http\Requests\Fees;

use App\Models\FeeRefund;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveFeeRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('issue-refunds');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', Rule::exists('students', 'id')->whereNull('deleted_at')],
            'source' => ['required', 'string', Rule::in(FeeRefund::SOURCES)],
            'amount' => ['required', 'integer', 'min:1'],
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->whereNull('deleted_at')],
            'refunded_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reason' => ['required', 'string', 'max:1000'],
            'reference_no' => ['nullable', 'string', 'max:60'],
        ];
    }
}

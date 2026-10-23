<?php

namespace App\Http\Requests\Hr;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Amount in paisa; employee_id is only used by payroll staff entering a loan for someone.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['sometimes', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'loan_type_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'loan_type')->whereNull('deleted_at')],
            'amount' => ['required', 'integer', 'min:100', 'max:100000000000'],
            'instalments' => ['required', 'integer', 'min:1', 'max:60'],
            'start_month' => ['required', 'date_format:Y-m'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}

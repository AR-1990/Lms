<?php

namespace App\Http\Requests\Expenses;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Lines the bank shows as cleared.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'statement_balance' => ['nullable', 'integer', 'min:-100000000000', 'max:100000000000'],
            'cleared' => ['array'],
            'cleared.*.journal_line_id' => ['required', 'integer'],
            'cleared.*.cleared_on' => ['nullable', 'date'],
        ];
    }
}

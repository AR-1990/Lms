<?php

namespace App\Http\Requests\Fees;

use App\Models\LateFeeRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveLateFeeRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A late fine: fixed (paisa, once), per_day (paisa a day) or percent (×100 of what was billed), after the grace days.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'grace_days' => ['nullable', 'integer', 'min:0', 'max:90'],
            'calculation' => ['required', Rule::in(LateFeeRule::CALCULATIONS)],
            'amount' => ['required', 'integer', 'min:1', $this->input('calculation') === 'percent' ? 'max:10000' : 'max:100000000'],
            'max_amount' => ['nullable', 'integer', 'min:1'],
            'fee_head_id' => ['nullable', 'integer', Rule::exists('fee_heads', 'id')->whereNull('deleted_at')],
            'invoice_type' => ['nullable', Rule::in(['monthly', 'admission', 'registration', 'exam', 'other'])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['amount.max' => 'A percent fine cannot be more than 100%.'];
    }
}

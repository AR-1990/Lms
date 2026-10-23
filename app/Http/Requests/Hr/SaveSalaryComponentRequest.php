<?php

namespace App\Http\Requests\Hr;

use App\Models\SalaryComponent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSalaryComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-payroll');
    }

    /**
     * default_value is paisa for fixed amounts, or percent × 100 for percent_of_basic.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', 'alpha_dash'],
            'type' => ['required', Rule::in(SalaryComponent::TYPES)],
            'calculation' => ['required', Rule::in(SalaryComponent::CALCULATIONS)],
            'default_value' => ['nullable', 'integer', 'min:0'],
            'is_taxable' => ['sometimes', 'boolean'],
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->whereNull('deleted_at')],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:32000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

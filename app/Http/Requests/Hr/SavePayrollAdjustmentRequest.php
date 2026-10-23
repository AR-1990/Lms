<?php

namespace App\Http\Requests\Hr;

use App\Models\SalaryComponent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePayrollAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A one-off amount (paisa) for one employee and month: bonus, incentive, overtime, arrears or a fine.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'month' => ['required', 'date_format:Y-m'],
            'salary_component_id' => ['required', 'integer', Rule::exists('salary_components', 'id')->where('is_active', true)->whereNotIn('code', SalaryComponent::SYSTEM_CODES)->whereNull('deleted_at')],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['employee_id' => 'employee', 'salary_component_id' => 'type'];
    }
}

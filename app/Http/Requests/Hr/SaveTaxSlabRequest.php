<?php

namespace App\Http\Requests\Hr;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTaxSlabRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-payroll');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fiscal_year_id' => ['required', 'integer', Rule::exists('fiscal_years', 'id')->whereNull('deleted_at')],
            'min_income' => ['required', 'integer', 'min:0'],
            'max_income' => ['nullable', 'integer', 'gt:min_income'],
            'fixed_tax' => ['nullable', 'integer', 'min:0'],
            'rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}

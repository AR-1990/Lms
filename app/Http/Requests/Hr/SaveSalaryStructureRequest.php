<?php

namespace App\Http\Requests\Hr;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSalaryStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-payroll');
    }

    /**
     * Amounts are paisa; an empty item amount takes the component's default.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'basic_salary' => ['required', 'integer', 'min:0', 'max:100000000000'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'items' => ['sometimes', 'array', 'max:40'],
            'items.*.salary_component_id' => ['required', 'integer', 'distinct', Rule::exists('salary_components', 'id')->where('is_active', 1)->whereNotIn('code', ['BASIC', 'ABSENT', 'LOAN'])->whereNull('deleted_at')],
            'items.*.amount' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
        ];
    }
}

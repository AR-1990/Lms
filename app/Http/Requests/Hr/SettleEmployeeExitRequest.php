<?php

namespace App\Http\Requests\Hr;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettleEmployeeExitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-hr');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payment_method_id' => [$this->routeIs('erp.employee-exits.settle') ? 'required' : 'nullable', 'integer', Rule::exists('payment_methods', 'id')->whereNull('deleted_at')],
            'paid_on' => [$this->routeIs('erp.employee-exits.settle') ? 'required' : 'nullable', 'date_format:Y-m-d'],
            'lines' => ['sometimes', 'array', 'max:20'],
            'lines.*.label' => ['required', 'string', 'max:255'],
            'lines.*.type' => ['required', Rule::in(['earning', 'deduction'])],
            'lines.*.amount' => ['required', 'integer', 'min:1'],
            'exit_interview_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

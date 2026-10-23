<?php

namespace App\Http\Requests\Assets;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteRepairRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Close a repair; a cost is posted as a repair expense.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'completed_on' => ['required', 'date', 'before_or_equal:today'],
            'cost' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'vendor_id' => ['nullable', 'integer', Rule::exists('vendors', 'id')->whereNull('deleted_at')],
            'payment_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')->whereNull('deleted_at')],
            'condition_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'asset_condition')->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

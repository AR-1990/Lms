<?php

namespace App\Http\Requests\Hr;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-purchases');
    }

    /**
     * Prices are in paisa; each line's total is quantity × unit price.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'request_date' => ['nullable', 'date_format:Y-m-d'],
            'needed_by' => ['nullable', 'date_format:Y-m-d'],
            'purpose' => ['required', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.category_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'purchase_category')->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:999999'],
            'items.*.unit_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'unit_of_measure')->whereNull('deleted_at')],
            'items.*.estimated_unit_price' => ['required', 'integer', 'min:0', 'max:9999999999'],
            'items.*.remarks' => ['nullable', 'string', 'max:255'],
        ];
    }
}

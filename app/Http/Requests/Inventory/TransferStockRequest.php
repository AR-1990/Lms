<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Stock moved between two stores.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from_location_id' => ['required', 'integer', Rule::exists('locations', 'id')->where('is_store', true)->whereNull('deleted_at')],
            'to_location_id' => ['required', 'integer', Rule::exists('locations', 'id')->where('is_store', true)->whereNull('deleted_at'), 'different:from_location_id'],
            'transferred_on' => ['required', 'date', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.inventory_item_id' => ['required', 'integer', Rule::exists('inventory_items', 'id')->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:999999999'],
        ];
    }
}

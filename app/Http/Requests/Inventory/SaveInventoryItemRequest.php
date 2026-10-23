<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A stock item. Costs and prices in paisa; the code is generated when left blank.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sku' => ['nullable', 'string', 'max:50', Rule::unique('inventory_items', 'sku')->ignore($this->route('inventory_item')?->id)->whereNull('deleted_at')],
            'item_category_id' => ['required', 'integer', Rule::exists('item_categories', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255'],
            'unit_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'unit_of_measure')->whereNull('deleted_at')],
            'brand' => ['nullable', 'string', 'max:100'],
            'size' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'reorder_quantity' => ['nullable', 'numeric', 'min:0'],
            'standard_cost' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'sale_price' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'is_sellable' => ['sometimes', 'boolean'],
            'is_returnable' => ['sometimes', 'boolean'],
            'track_batches' => ['sometimes', 'boolean'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

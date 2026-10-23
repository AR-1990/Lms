<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A draft purchase order; each line is a stock item or an asset category.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vendor_id' => ['required', 'integer', Rule::exists('vendors', 'id')->whereNull('deleted_at')],
            'purchase_request_id' => ['nullable', 'integer', Rule::exists('purchase_requests', 'id')->where('status', 'approved')->whereNull('deleted_at')],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('is_store', true)->whereNull('deleted_at')],
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'tax' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'discount' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.inventory_item_id' => ['nullable', 'integer', Rule::exists('inventory_items', 'id')->whereNull('deleted_at'), 'required_without:items.*.asset_category_id'],
            'items.*.asset_category_id' => ['nullable', 'integer', Rule::exists('asset_categories', 'id')->whereNull('deleted_at')],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:999999999'],
            'items.*.unit_cost' => ['required', 'integer', 'min:0', 'max:100000000000'],
        ];
    }
}

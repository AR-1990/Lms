<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceiveGoodsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Goods received into a store, against an order or directly from a vendor.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'purchase_order_id' => ['nullable', 'integer', Rule::exists('purchase_orders', 'id')->whereNull('deleted_at')],
            'vendor_id' => ['nullable', 'integer', Rule::exists('vendors', 'id')->whereNull('deleted_at'), 'required_without:purchase_order_id'],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')->where('is_store', true)->whereNull('deleted_at')],
            'vendor_invoice_no' => ['nullable', 'string', 'max:100'],
            'vendor_invoice_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.purchase_order_item_id' => ['nullable', 'integer'],
            'items.*.inventory_item_id' => ['nullable', 'integer', Rule::exists('inventory_items', 'id')->whereNull('deleted_at')],
            'items.*.asset_category_id' => ['nullable', 'integer', Rule::exists('asset_categories', 'id')->whereNull('deleted_at')],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'items.*.unit_cost' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'items.*.batch_no' => ['nullable', 'string', 'max:50'],
            'items.*.expiry_date' => ['nullable', 'date'],
        ];
    }
}

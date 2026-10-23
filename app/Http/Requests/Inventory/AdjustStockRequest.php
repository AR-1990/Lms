<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('approve-requests');
    }

    /**
     * A stock count (or opening stock). Only approvers post counts; the difference goes to the ledger.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')->where('is_store', true)->whereNull('deleted_at')],
            'adjusted_on' => ['required', 'date', 'before_or_equal:today'],
            'reason_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'stock_adjustment_reason')->whereNull('deleted_at')],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.inventory_item_id' => ['required', 'integer', Rule::exists('inventory_items', 'id')->whereNull('deleted_at'), 'distinct'],
            'items.*.counted_qty' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'items.*.unit_cost' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
        ];
    }
}

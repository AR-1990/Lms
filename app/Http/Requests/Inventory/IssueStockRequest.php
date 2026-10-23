<?php

namespace App\Http\Requests\Inventory;

use App\Models\StockIssue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Stock issued (or sold to a student) from a store.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'issued_on' => ['required', 'date', 'before_or_equal:today'],
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')->where('is_store', true)->whereNull('deleted_at')],
            'recipient_type' => ['required', Rule::in(array_keys(StockIssue::RECIPIENTS))],
            'recipient_id' => ['required', 'integer'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'purchase_request_id' => ['nullable', 'integer', Rule::exists('purchase_requests', 'id')->whereNull('deleted_at')],
            'received_signature' => ['sometimes', 'boolean'],
            'is_sale' => ['sometimes', 'boolean'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issued_on'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.inventory_item_id' => ['required', 'integer', Rule::exists('inventory_items', 'id')->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:999999999'],
            'items.*.sale_price' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'items.*.due_back_on' => ['nullable', 'date', 'after_or_equal:issued_on'],
        ];
    }
}

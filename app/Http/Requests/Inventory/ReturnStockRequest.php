<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReturnStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Items given back against an issue.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'returned_on' => ['required', 'date', 'before_or_equal:today'],
            'condition_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'asset_condition')->whereNull('deleted_at')],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.stock_issue_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ];
    }
}

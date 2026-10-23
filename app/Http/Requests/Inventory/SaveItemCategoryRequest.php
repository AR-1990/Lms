<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveItemCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * An item category with its ledger accounts and the fee head used when its items are sold.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'parent_id' => ['nullable', 'integer', Rule::exists('item_categories', 'id')->whereNull('deleted_at'), Rule::notIn([$this->route('item_category')?->id])],
            'inventory_account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->whereNull('deleted_at')],
            'expense_account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->whereNull('deleted_at')],
            'income_account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->whereNull('deleted_at')],
            'sale_fee_head_id' => ['nullable', 'integer', Rule::exists('fee_heads', 'id')->whereNull('deleted_at')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

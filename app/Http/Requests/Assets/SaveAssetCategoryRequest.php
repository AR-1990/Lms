<?php

namespace App\Http\Requests\Assets;

use App\Models\AssetCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAssetCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A category with its ledger accounts and depreciation rule.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:20'],
            'parent_id' => ['nullable', 'integer', Rule::exists('asset_categories', 'id')->whereNull('deleted_at'), Rule::notIn([$this->route('asset_category')?->id])],
            'asset_account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->whereNull('deleted_at')],
            'depreciation_account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->whereNull('deleted_at')],
            'accumulated_depreciation_account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->whereNull('deleted_at')],
            'depreciation_method' => ['required', Rule::in(AssetCategory::METHODS)],
            'useful_life_months' => ['nullable', 'integer', 'min:1', 'max:1200', 'required_unless:depreciation_method,none'],
            'salvage_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

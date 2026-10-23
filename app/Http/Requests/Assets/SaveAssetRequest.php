<?php

namespace App\Http\Requests\Assets;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * An asset in the register. Money is in paisa; the tag is generated when left blank.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'asset_tag' => ['nullable', 'string', 'max:50', Rule::unique('assets', 'asset_tag')->ignore($this->route('asset')?->id)->whereNull('deleted_at')],
            'asset_category_id' => ['required', 'integer', Rule::exists('asset_categories', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_no' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'vendor_id' => ['nullable', 'integer', Rule::exists('vendors', 'id')->whereNull('deleted_at')],
            'purchase_date' => ['nullable', 'date', 'before_or_equal:today'],
            'purchase_cost' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'invoice_no' => ['nullable', 'string', 'max:100'],
            'warranty_expires_on' => ['nullable', 'date'],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->whereNull('deleted_at')],
            'condition_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'asset_condition')->whereNull('deleted_at')],
            'depreciation_start_date' => ['nullable', 'date'],
            'post_purchase' => ['sometimes', 'boolean'],
            'payment_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')->whereNull('deleted_at')],
        ];
    }
}

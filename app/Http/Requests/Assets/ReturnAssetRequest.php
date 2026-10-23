<?php

namespace App\Http\Requests\Assets;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReturnAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Take an asset back.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'returned_on' => ['required', 'date', 'before_or_equal:today'],
            'condition_in_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'asset_condition')->whereNull('deleted_at')],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->whereNull('deleted_at')],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

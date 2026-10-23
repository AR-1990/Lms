<?php

namespace App\Http\Requests\Assets;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Ticks of a verification; complete closes it.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => ['array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.found' => ['nullable', 'boolean'],
            'items.*.condition_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'asset_condition')->whereNull('deleted_at')],
            'items.*.remarks' => ['nullable', 'string', 'max:255'],
            'complete' => ['sometimes', 'boolean'],
            'mark_missing_lost' => ['sometimes', 'boolean'],
        ];
    }
}

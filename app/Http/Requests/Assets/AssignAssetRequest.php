<?php

namespace App\Http\Requests\Assets;

use App\Models\Asset;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Hand an asset to an employee, student, department or room.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'assignee_type' => ['required', Rule::in(array_keys(Asset::HOLDERS))],
            'assignee_id' => ['required', 'integer'],
            'assigned_on' => ['required', 'date', 'before_or_equal:today'],
            'expected_return_on' => ['nullable', 'date', 'after_or_equal:assigned_on'],
            'condition_out_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'asset_condition')->whereNull('deleted_at')],
            'acknowledgement_signed' => ['sometimes', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

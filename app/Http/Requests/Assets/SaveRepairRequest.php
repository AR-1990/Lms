<?php

namespace App\Http\Requests\Assets;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRepairRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Report or update a repair job.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'maintenance_type_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'maintenance_type')->whereNull('deleted_at')],
            'vendor_id' => ['nullable', 'integer', Rule::exists('vendors', 'id')->whereNull('deleted_at')],
            'reported_on' => ['required', 'date', 'before_or_equal:today'],
            'sent_on' => ['nullable', 'date', 'after_or_equal:reported_on'],
            'cost' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

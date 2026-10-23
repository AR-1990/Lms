<?php

namespace App\Http\Requests\Assets;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Start a physical verification of one location or all.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'audit_date' => ['required', 'date', 'before_or_equal:today'],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->whereNull('deleted_at')],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

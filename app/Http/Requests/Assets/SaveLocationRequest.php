<?php

namespace App\Http\Requests\Assets;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A room, lab, office or store.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:20'],
            'building' => ['nullable', 'string', 'max:100'],
            'floor' => ['nullable', 'string', 'max:50'],
            'parent_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->whereNull('deleted_at'), Rule::notIn([$this->route('location')?->id])],
            'in_charge_employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'is_store' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

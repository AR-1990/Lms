<?php

namespace App\Http\Requests\ERP;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $roleId = $this->route('role')?->id ?? $this->route('role');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', Rule::unique('roles', 'slug')->ignore($roleId)],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ];
    }
}

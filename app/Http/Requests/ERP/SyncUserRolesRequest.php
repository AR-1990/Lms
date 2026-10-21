<?php

namespace App\Http\Requests\ERP;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SyncUserRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-roles');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'roles' => ['present', 'array'],
            'roles.*' => ['string', 'distinct', 'exists:roles,slug'],
        ];
    }
}

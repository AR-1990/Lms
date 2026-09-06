<?php

namespace App\Http\Requests\ERP;

use Illuminate\Foundation\Http\FormRequest;

class AssignRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['required'], // can be role slug or role id
        ];
    }
}

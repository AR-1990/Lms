<?php

namespace App\Http\Requests\ERP;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            // Public sign-up may never pick a staff or admin role; those are granted by an admin.
            'role' => ['nullable', 'string', Rule::in(['student', 'parent'])],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}

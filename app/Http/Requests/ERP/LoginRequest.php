<?php

namespace App\Http\Requests\ERP;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `email` accepts the account's email, username, or phone number.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'device_id' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'in:android,ios,web'],
            'app_version' => ['nullable', 'string', 'max:20'],
            'push_token' => ['nullable', 'string', 'max:255'],
        ];
    }
}

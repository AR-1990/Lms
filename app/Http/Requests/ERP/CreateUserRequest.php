<?php

namespace App\Http\Requests\ERP;

use App\Http\Requests\Concerns\ValidatesUserAccount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
{
    use ValidatesUserAccount;

    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-users');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->accountRules(),
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ];
    }
}

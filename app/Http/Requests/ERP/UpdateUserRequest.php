<?php

namespace App\Http\Requests\ERP;

use App\Http\Requests\Concerns\ValidatesUserAccount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
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
            ...$this->accountRules($this->route('user')),
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user')->id)],
            'password' => ['nullable', 'string', 'min:8'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->guardOwnAccount($validator, $this->route('user')));
    }
}

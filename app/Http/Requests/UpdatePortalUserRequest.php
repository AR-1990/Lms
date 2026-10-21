<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesUserAccount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePortalUserRequest extends FormRequest
{
    use ValidatesUserAccount;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermission('manage-users');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->accountRules($this->route('user')),
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user')->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'roles' => ['required', 'array', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->guardOwnAccount($validator, $this->route('user')));
    }
}

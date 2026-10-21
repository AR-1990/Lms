<?php

namespace App\Http\Requests\Concerns;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Account rules shared by the web user forms and the /api/erp/users endpoints.
 */
trait ValidatesUserAccount
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function accountRules(?User $account = null): array
    {
        return [
            'username' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($account?->id)],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 -]{6,28}$/', Rule::unique('users', 'phone')->ignore($account?->id)],
            'is_active' => ['sometimes', 'boolean'],
            'campus_id' => ['nullable', 'integer', Rule::exists('campuses', 'id')->whereNull('deleted_at')],
            'roles' => ['array'],
            'roles.*' => ['string', 'distinct', 'exists:roles,slug'],
        ];
    }

    /**
     * Stop an administrator from disabling their own account
     * (removing their own Administrator role is blocked in UserService::syncRoles).
     */
    protected function guardOwnAccount(Validator $validator, ?User $account): void
    {
        if (! $account?->is($this->user())) {
            return;
        }

        if ($this->has('is_active') && ! $this->boolean('is_active')) {
            $validator->errors()->add('is_active', 'You cannot disable your own account.');
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'roles.required' => 'Select at least one role for this user.',
            'username.regex' => 'Use only letters, numbers, dots, dashes and underscores.',
            'phone.regex' => 'Enter a valid phone number, e.g. 03001234567.',
        ];
    }
}

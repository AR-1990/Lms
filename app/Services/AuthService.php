<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Authenticate a user and create a personal access token.
     *
     * @param array $credentials
     * @param string $deviceName
     * @return array
     * @throws ValidationException
     */
    public function login(array $credentials, string $deviceName = 'mobile-or-web'): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password.'],
            ]);
        }

        // Generate Sanctum Token
        $token = $user->createToken($deviceName)->plainTextToken;

        return [
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->loadMissing(['roles.permissions']),
        ];
    }

    /**
     * Register a new user and assign default/specified role.
     *
     * @param array $data
     * @param string $roleSlug
     * @param string $deviceName
     * @return array
     */
    public function register(array $data, string $roleSlug = 'student', string $deviceName = 'mobile-or-web'): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        // Find or fallback to student role
        $role = Role::where('slug', $roleSlug)->first();
        if ($role) {
            $user->assignRole($role);
        }

        $token = $user->createToken($deviceName)->plainTextToken;

        return [
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->loadMissing(['roles.permissions']),
        ];
    }

    /**
     * Logout and revoke current token.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    /**
     * Get user profile details with roles and permissions directly.
     */
    public function getProfile(User $user): User
    {
        return $user->loadMissing(['roles.permissions']);
    }

    /**
     * Update user profile.
     */
    public function updateProfile(User $user, array $data): User
    {
        $user->update([
            'name' => $data['name'] ?? $user->name,
            'email' => $data['email'] ?? $user->email,
        ]);

        return $user->loadMissing(['roles.permissions']);
    }

    /**
     * Change user password.
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): bool
    {
        if (!Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided password does not match your current password.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        return true;
    }
}

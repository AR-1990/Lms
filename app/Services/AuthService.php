<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(private PortalAccessService $portalAccessService) {}

    /**
     * Authenticate a user and create a personal access token.
     *
     * @throws ValidationException
     */
    public function login(array $credentials, string $deviceName = 'mobile-or-web'): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password.'],
            ]);
        }

        $token = $user->createToken($deviceName)->plainTextToken;
        $payload = $this->buildAuthenticatedPayload($user->fresh(), $deviceName);

        return [
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $payload['user'],
            'authorization' => $payload['authorization'],
        ];
    }

    /**
     * Register a new user and assign default/specified role.
     */
    public function register(array $data, string $roleSlug = 'student', string $deviceName = 'mobile-or-web'): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $role = Role::where('slug', $roleSlug)->first();

        if ($role) {
            $user->assignRole($role);
        }

        $token = $user->createToken($deviceName)->plainTextToken;
        $payload = $this->buildAuthenticatedPayload($user->fresh(), $deviceName);

        return [
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $payload['user'],
            'authorization' => $payload['authorization'],
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
    public function getProfile(User $user, ?string $deviceName = null): array
    {
        return $this->buildAuthenticatedPayload($user, $deviceName);
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
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided password does not match your current password.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        return true;
    }

    /**
     * @return array{user: User, authorization: array<string, mixed>}
     */
    private function buildAuthenticatedPayload(User $user, ?string $deviceName = null): array
    {
        $authenticatedUser = $user->loadMissing(['roles.permissions']);
        $roles = $authenticatedUser->roles->pluck('slug')->filter()->values();
        $permissions = $authenticatedUser->roles
            ->pluck('permissions')
            ->flatten()
            ->pluck('slug')
            ->filter()
            ->unique()
            ->values();
        $currentAccessToken = $authenticatedUser->currentAccessToken();

        return [
            'user' => $authenticatedUser,
            'authorization' => [
                'role' => $roles->first(),
                'roles' => $roles->all(),
                'permissions' => $permissions->all(),
                'portals' => $this->portalAccessService->getAccessiblePortals($authenticatedUser),
                'is_admin' => $authenticatedUser->hasRole('admin'),
                'device_name' => $deviceName ?? $currentAccessToken?->name,
                'token' => $this->formatCurrentAccessToken($currentAccessToken),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatCurrentAccessToken(mixed $token): ?array
    {
        if ($token === null) {
            return null;
        }

        return [
            'id' => $token->id,
            'name' => $token->name,
            'last_used_at' => $token->last_used_at,
            'expires_at' => $token->expires_at,
        ];
    }
}

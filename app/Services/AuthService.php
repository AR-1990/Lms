<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Models\UserDevice;
use App\Support\ValidationHelper;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private PortalAccessService $portalAccessService,
        private ActivityLogger $activityLogger,
    ) {}

    /**
     * Authenticate a user and create a personal access token.
     *
     * @throws ValidationException
     */
    /**
     * `email` may be the account's email, username, or phone. `$device` carries
     * device_id / platform / app_version / push_token for the user_devices record.
     *
     * @param  array{email: string, password: string}  $credentials
     * @param  array{device_id?: string|null, platform?: string|null, app_version?: string|null, push_token?: string|null}  $device
     */
    public function login(array $credentials, string $deviceName = 'mobile-or-web', array $device = []): array
    {
        $user = User::findForLogin($credentials['email']);

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            $this->activityLogger->record('auth', 'login_failed', "Failed API sign-in attempt for \"{$credentials['email']}\"");

            throw ValidationHelper::exception([
                'email' => ['Invalid email or password.'],
            ]);
        }

        if (! $user->is_active) {
            $this->activityLogger->record('auth', 'login_failed', "{$user->name} tried to sign in to a disabled account via API", $user, actor: $user);

            throw ValidationHelper::exception([
                'email' => ['This account has been disabled. Please contact the school office.'],
            ]);
        }

        $newToken = $user->createToken($deviceName);
        $token = $newToken->plainTextToken;
        $this->registerDevice($user, $newToken->accessToken->getKey(), $deviceName, $device);
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => request()->ip()])->saveQuietly();
        $this->activityLogger->record('auth', 'login', "{$user->name} signed in via API ({$deviceName})", $user, actor: $user);
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
        $this->activityLogger->record('auth', 'logout', "{$user->name} signed out via API", $user, actor: $user);

        $token = $user->currentAccessToken();

        if ($token && method_exists($token, 'getKey')) {
            UserDevice::where('personal_access_token_id', $token->getKey())->update(['is_active' => false]);
        }

        $token?->delete();
    }

    /**
     * One row per signed-in app/browser; a repeat login from the same device_id reuses its row.
     *
     * @param  array{device_id?: string|null, platform?: string|null, app_version?: string|null, push_token?: string|null}  $device
     */
    private function registerDevice(User $user, int $tokenId, string $deviceName, array $device): UserDevice
    {
        $attributes = [
            'platform' => $device['platform'] ?? 'web',
            'device_name' => Str::limit($deviceName, 250, ''),
            'app_version' => $device['app_version'] ?? null,
            'push_token' => $device['push_token'] ?? null,
            'personal_access_token_id' => $tokenId,
            'last_active_at' => now(),
            'is_active' => true,
        ];

        if (filled($device['device_id'] ?? null)) {
            return UserDevice::updateOrCreate(['user_id' => $user->id, 'device_id' => $device['device_id']], $attributes);
        }

        return UserDevice::create(['user_id' => $user->id, ...$attributes]);
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
            throw ValidationHelper::exception([
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

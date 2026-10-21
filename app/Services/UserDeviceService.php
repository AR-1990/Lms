<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class UserDeviceService
{
    public function __construct(private ActivityLogger $activityLogger) {}

    /**
     * The user's signed-in apps and browsers, most recently used first.
     *
     * @return Collection<int, UserDevice>
     */
    public function forUser(User $user): Collection
    {
        return $user->devices()->where('is_active', true)->latest('last_active_at')->get();
    }

    /**
     * Save the push token of the device making this request (identified by its API token).
     */
    public function updatePushToken(User $user, string $pushToken): ?UserDevice
    {
        $device = $this->currentDevice($user);

        $device?->update(['push_token' => $pushToken, 'last_active_at' => now()]);

        return $device;
    }

    /**
     * Sign a device out remotely: its API token is deleted and the device deactivated.
     */
    public function revoke(User $user, UserDevice $device): void
    {
        DB::transaction(function () use ($user, $device): void {
            if ($device->personal_access_token_id) {
                PersonalAccessToken::whereKey($device->personal_access_token_id)->delete();
            }

            $device->update(['is_active' => false]);

            $this->activityLogger->record('auth', 'device_revoked', "{$user->name} signed out the device \"{$device->device_name}\"", $user);
        });
    }

    public function currentDevice(User $user): ?UserDevice
    {
        $token = $user->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            return null;
        }

        return $user->devices()->where('personal_access_token_id', $token->getKey())->first();
    }
}

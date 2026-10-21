<?php

namespace App\Http\Resources;

use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserDevice
 */
class UserDeviceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentToken = $request->user()?->currentAccessToken();

        return [
            'id' => $this->id,
            'platform' => $this->platform,
            'device_name' => $this->device_name,
            'app_version' => $this->app_version,
            'has_push_token' => filled($this->push_token),
            'last_active_at' => $this->last_active_at?->toIso8601String(),
            'is_current' => $currentToken !== null
                && method_exists($currentToken, 'getKey')
                && $currentToken->getKey() === $this->personal_access_token_id,
        ];
    }
}

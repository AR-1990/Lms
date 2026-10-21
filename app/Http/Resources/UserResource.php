<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'username' => $this->username,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'campus_id' => $this->campus_id,
            'campus' => $this->whenLoaded('campus', fn (): ?array => $this->campus ? ['id' => $this->campus->id, 'name' => $this->campus->name, 'code' => $this->campus->code] : null),
            'roles' => $this->whenLoaded('roles', fn (): array => $this->roles->map(fn ($role): array => ['id' => $role->id, 'name' => $role->name, 'slug' => $role->slug])->all()),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

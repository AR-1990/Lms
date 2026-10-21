<?php

namespace App\Http\Resources;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
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
            'slug' => $this->slug,
            'description' => $this->description,
            'is_system' => $this->is_system,
            'users_count' => $this->whenCounted('users'),
            'permissions' => $this->whenLoaded('permissions', fn (): array => $this->permissions
                ->map(fn ($permission): array => ['id' => $permission->id, 'name' => $permission->name, 'slug' => $permission->slug, 'group' => $permission->group])
                ->all()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\ValidationHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class RolePermissionService
{
    /**
     * Get all roles with their permissions count and user counts.
     */
    public function getAllRoles(): Collection
    {
        return Role::with(['permissions'])->withCount('users')->get();
    }

    /**
     * Get role by ID or slug.
     */
    public function getRole(int|string $idOrSlug): Role
    {
        if (is_numeric($idOrSlug)) {
            return Role::with(['permissions', 'users'])->findOrFail($idOrSlug);
        }

        return Role::with(['permissions', 'users'])->where('slug', $idOrSlug)->firstOrFail();
    }

    /**
     * Create a new dynamic role.
     */
    public function createRole(array $data): Role
    {
        $slug = ! empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['name']);

        if (Role::where('slug', $slug)->exists()) {
            throw ValidationHelper::exception([
                'slug' => ['A role with this slug already exists.'],
            ]);
        }

        $role = Role::create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'is_system' => $data['is_system'] ?? false,
        ]);

        if (! empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return $role->load('permissions');
    }

    /**
     * Update an existing role.
     */
    public function updateRole(Role $role, array $data): Role
    {
        if ($role->is_system && isset($data['slug']) && $data['slug'] !== $role->slug) {
            throw ValidationHelper::exception([
                'slug' => ['System roles cannot change their unique slug.'],
            ]);
        }

        $slug = ! empty($data['slug']) ? Str::slug($data['slug']) : $role->slug;

        $role->update([
            'name' => $data['name'] ?? $role->name,
            'slug' => $slug,
            'description' => array_key_exists('description', $data) ? $data['description'] : $role->description,
        ]);

        if (isset($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return $role->load('permissions');
    }

    /**
     * Delete a role (unless it is a protected system role).
     */
    public function deleteRole(Role $role): bool
    {
        if ($role->is_system) {
            throw ValidationHelper::exception([
                'role' => ['System roles cannot be deleted.'],
            ]);
        }

        return (bool) $role->delete();
    }

    /**
     * Assign a role to a user.
     */
    public function assignRoleToUser(int $userId, string|int $role): User
    {
        $user = User::findOrFail($userId);
        $user->assignRole($role);

        return $user->load('roles');
    }

    /**
     * Revoke a role from a user.
     */
    public function revokeRoleFromUser(int $userId, string|int $role): User
    {
        $user = User::findOrFail($userId);
        $user->removeRole($role);

        return $user->load('roles');
    }

    /**
     * Sync multiple roles for a user.
     */
    public function syncUserRoles(int $userId, array $roles): User
    {
        $user = User::findOrFail($userId);
        $user->syncRoles($roles);

        return $user->load('roles');
    }

    /**
     * List all permissions grouped by category.
     */
    public function getAllPermissions(): Collection
    {
        return Permission::all();
    }

    /**
     * Create a new permission.
     */
    public function createPermission(array $data): Permission
    {
        $slug = ! empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['name']);

        if (Permission::where('slug', $slug)->exists()) {
            throw ValidationHelper::exception([
                'slug' => ['A permission with this slug already exists.'],
            ]);
        }

        return Permission::create([
            'name' => $data['name'],
            'slug' => $slug,
            'group' => $data['group'] ?? 'general',
            'description' => $data['description'] ?? null,
        ]);
    }
}

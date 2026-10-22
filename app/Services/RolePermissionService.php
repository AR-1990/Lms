<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class RolePermissionService
{
    /**
     * The Administrator role passes every permission check, so its permission list is never edited.
     */
    public const ADMIN_ROLE = 'admin';

    public function __construct(
        private UserService $userService,
        private ActivityLogger $activityLogger,
    ) {}

    /**
     * @return Collection<int, Role>
     */
    public function roles(): Collection
    {
        return Role::with('permissions:id,name,slug,group')->withCount('users')->orderBy('name')->get();
    }

    /**
     * Server-side grid for the roles screen.
     *
     * @return array<string, mixed>
     */
    public function datatable(): array
    {
        $query = Role::query()
            ->select(['id', 'name', 'slug', 'description', 'is_system', 'created_at'])
            ->with('permissions:id,slug')
            ->withCount(['users', 'permissions']);

        return DataTables::eloquent($query)
            ->escapeColumns([])
            ->addColumn('permission_slugs', fn (Role $role): array => $role->permissions->pluck('slug')->all())
            ->addColumn('is_admin', fn (Role $role): bool => $role->slug === self::ADMIN_ROLE)
            ->removeColumn('permissions')
            ->toArray();
    }

    /**
     * @return Collection<int, Permission>
     */
    public function permissions(): Collection
    {
        return Permission::query()->orderBy('group')->orderBy('name')->get(['id', 'name', 'slug', 'group', 'description']);
    }

    /**
     * Permissions grouped by module, for the permission matrix.
     *
     * @return SupportCollection<string, Collection<int, Permission>>
     */
    public function permissionGroups(): SupportCollection
    {
        return $this->permissions()->groupBy('group');
    }

    /**
     * @param  array{name: string, slug: string, description?: string|null, permissions?: array<int, string>}  $data
     */
    public function createRole(array $data): Role
    {
        return DB::transaction(function () use ($data): Role {
            $role = Role::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
                'is_system' => false,
            ]);

            $this->syncPermissions($role, $data['permissions'] ?? []);

            return $role->load('permissions')->loadCount('users');
        });
    }

    /**
     * @param  array{name?: string, slug?: string, description?: string|null, permissions?: array<int, string>}  $data
     *
     * @throws ValidationException
     */
    public function updateRole(Role $role, array $data): Role
    {
        if ($role->is_system && isset($data['slug']) && $data['slug'] !== $role->slug) {
            throw ValidationException::withMessages(['slug' => ['Built-in roles keep their slug.']]);
        }

        return DB::transaction(function () use ($role, $data): Role {
            $role->update(collect($data)->only(['name', 'slug', 'description'])->all());

            if (array_key_exists('permissions', $data)) {
                $this->syncPermissions($role, $data['permissions'] ?? []);
            }

            return $role->load('permissions')->loadCount('users');
        });
    }

    /**
     * @throws ValidationException for built-in roles and roles still assigned to users
     */
    public function deleteRole(Role $role): void
    {
        if ($role->is_system) {
            throw ValidationException::withMessages(['role' => ['System roles cannot be deleted.']]);
        }

        $usersCount = $role->users()->count();

        if ($usersCount > 0) {
            throw ValidationException::withMessages([
                'role' => ["Remove this role from its {$usersCount} ".str('user')->plural($usersCount).' before deleting it.'],
            ]);
        }

        DB::transaction(fn () => $role->delete());
    }

    /**
     * Replace a role's permissions (ids or slugs) and log what was granted and revoked.
     *
     * @param  array<int, string|int>  $permissions
     *
     * @throws ValidationException for the Administrator role
     */
    public function syncPermissions(Role $role, array $permissions): Role
    {
        if ($role->slug === self::ADMIN_ROLE) {
            throw ValidationException::withMessages(['permissions' => ['The Administrator role always has full access.']]);
        }

        $before = $role->permissions()->pluck('slug');
        $role->syncPermissions($permissions);
        $after = $role->permissions()->pluck('slug');

        $granted = $after->diff($before)->sort()->values();
        $revoked = $before->diff($after)->sort()->values();

        if ($granted->isNotEmpty() || $revoked->isNotEmpty()) {
            $this->activityLogger->record(
                'roles',
                'permissions_changed',
                (request()->user()?->name ?? 'System')." changed the permissions of {$role->name}",
                $role,
                [
                    'granted' => ['old' => null, 'new' => $granted->implode(', ') ?: null],
                    'revoked' => ['old' => $revoked->implode(', ') ?: null, 'new' => null],
                ],
            );
        }

        return $role->load('permissions');
    }

    public function assignRoleToUser(User $user, string|int $role): User
    {
        $slug = $this->resolveRole($role)->slug;
        $this->userService->syncRoles($user, $user->roles()->pluck('slug')->push($slug)->unique()->all());

        return $user->load('roles');
    }

    public function revokeRoleFromUser(User $user, string|int $role): User
    {
        $slug = $this->resolveRole($role)->slug;
        $this->userService->syncRoles($user, $user->roles()->pluck('slug')->reject(fn (string $current): bool => $current === $slug)->all());

        return $user->load('roles');
    }

    /**
     * @param  array<int, string|int>  $roles
     */
    public function syncUserRoles(User $user, array $roles): User
    {
        $this->userService->syncRoles($user, $roles);

        return $user->load('roles');
    }

    /**
     * @param  array{name: string, slug: string, group?: string|null, description?: string|null}  $data
     */
    public function createPermission(array $data): Permission
    {
        return Permission::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'group' => $data['group'] ?? 'general',
            'description' => $data['description'] ?? null,
        ]);
    }

    private function resolveRole(string|int $role): Role
    {
        return Role::where('slug', (string) $role)->orWhere('id', (int) $role)->firstOrFail();
    }
}

<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class UserService
{
    /**
     * Profile fields an admin may set on an account (password and roles are handled separately).
     *
     * @var array<int, string>
     */
    private const PROFILE_FIELDS = ['name', 'email', 'username', 'phone', 'is_active', 'campus_id'];

    public function __construct(private ActivityLogger $activityLogger) {}

    /**
     * Paginated users for the mobile app / API consumers.
     *
     * @param  array{role?: string|null, search?: string|null, is_active?: string|null}  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query($filters)
            ->with(['roles:id,name,slug', 'campus:id,name,code'])
            ->latest()
            ->latest('id')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * Counters for the user directory header.
     *
     * @return array{total: int, active: int, inactive: int}
     */
    public function summary(): array
    {
        $active = User::where('is_active', true)->count();
        $total = User::count();

        return ['total' => $total, 'active' => $active, 'inactive' => $total - $active];
    }

    /**
     * @param  array{role?: string|null, search?: string|null, is_active?: string|null}  $filters
     */
    public function query(array $filters = []): Builder
    {
        return User::query()
            ->when(filled($filters['role'] ?? null), fn (Builder $query) => $query->whereHas('roles', fn (Builder $roles) => $roles->where('slug', $filters['role'])))
            ->when(isset($filters['is_active']) && $filters['is_active'] !== '', fn (Builder $query) => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)))
            ->when(filled($filters['search'] ?? null), fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('name', 'like', '%'.$filters['search'].'%')
                ->orWhere('email', 'like', '%'.$filters['search'].'%')
                ->orWhere('username', 'like', '%'.$filters['search'].'%')
                ->orWhere('phone', 'like', '%'.$filters['search'].'%')));
    }

    /**
     * Server-side grid for the admin user directory.
     *
     * @param  array{role?: string|null, is_active?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function datatable(array $filters): array
    {
        $query = $this->query($filters)
            ->select(['id', 'name', 'email', 'username', 'phone', 'is_active', 'last_login_at', 'created_at'])
            ->with('roles:id,name,slug');

        return DataTables::eloquent($query)
            ->escapeColumns([])
            ->addColumn('roles_list', fn (User $user): array => $user->roles->map(fn ($role): array => ['slug' => $role->slug, 'name' => $role->name])->all())
            ->addColumn('is_self', fn (User $user): bool => $user->is(request()->user()))
            ->editColumn('created_at', fn (User $user): ?string => $user->created_at?->toIso8601String())
            ->editColumn('last_login_at', fn (User $user): ?string => $user->last_login_at?->toIso8601String())
            ->toArray();
    }

    /**
     * Create a user with specified roles.
     */
    public function createUser(array $data, array|string|null $roles = null): User
    {
        return DB::transaction(function () use ($data, $roles): User {
            $user = User::create([
                ...collect($data)->only(self::PROFILE_FIELDS)->all(),
                'is_active' => $data['is_active'] ?? true,
                'password' => Hash::make($data['password']),
            ]);

            $normalizedRoles = $this->normalizeRoles($roles);

            if ($normalizedRoles !== []) {
                $this->syncRoles($user, $normalizedRoles);
            }

            return $user->load('roles');
        });
    }

    /**
     * Update user details and roles. Role changes are written to the activity log.
     */
    public function updateUser(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $updateData = collect($data)->only(self::PROFILE_FIELDS)->all();

            if (! empty($data['password'])) {
                $updateData['password'] = Hash::make($data['password']);
            }

            $user->update($updateData);

            if (array_key_exists('roles', $data)) {
                $this->syncRoles($user, $this->normalizeRoles($data['roles']));
            }

            if (array_key_exists('is_active', $data) && ! $data['is_active']) {
                $this->signOutEverywhere($user);
            }

            return $user->load('roles');
        });
    }

    /**
     * Soft delete the account and sign it out of every device.
     *
     * @throws ValidationException when an admin tries to delete their own account
     */
    public function deleteUser(User $user): bool
    {
        if ($user->is(request()->user())) {
            throw ValidationException::withMessages([
                'user' => ['You cannot delete your own account.'],
            ]);
        }

        return DB::transaction(function () use ($user): bool {
            $this->signOutEverywhere($user);

            return (bool) $user->delete();
        });
    }

    /**
     * Replace a user's roles (ids or slugs). Every role change goes through here so it is
     * logged, and an administrator can never strip their own Administrator role.
     *
     * @param  array<int, string|int>  $roles
     *
     * @throws ValidationException
     */
    public function syncRoles(User $user, array $roles): void
    {
        $before = $user->roles()->pluck('slug')->sort()->values()->all();
        $target = Role::whereIn('id', $roles)->orWhereIn('slug', $roles)->get(['id', 'slug']);
        $after = $target->pluck('slug')->sort()->values()->all();

        if ($user->is(request()->user()) && in_array('admin', $before, true) && ! in_array('admin', $after, true)) {
            throw ValidationException::withMessages([
                'roles' => ['You cannot remove the Administrator role from your own account.'],
            ]);
        }

        $user->roles()->sync($target->pluck('id'));

        if ($before !== $after) {
            $this->activityLogger->record(
                'users',
                'roles_changed',
                (request()->user()?->name ?? 'System')." changed the roles of {$user->name}",
                $user,
                ['roles' => ['old' => implode(', ', $before), 'new' => implode(', ', $after)]],
            );
        }
    }

    /**
     * End every mobile token and every web session of the account (disabled, deleted or password reset).
     * Tokens and sessions are security data, so they are removed outright.
     */
    public function signOutEverywhere(User $user): void
    {
        $user->tokens()->delete();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        UserDevice::where('user_id', $user->id)->update(['is_active' => false]);
    }

    /**
     * @return array<int, string>
     */
    private function normalizeRoles(array|string|null $roles): array
    {
        if ($roles === null) {
            return [];
        }

        if (is_string($roles)) {
            return [$roles];
        }

        return array_values(array_filter($roles, fn ($role) => $role !== null && $role !== ''));
    }
}

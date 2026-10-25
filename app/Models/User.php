<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\LogsActivity;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'username', 'phone', 'photo_path', 'is_active', 'must_change_password', 'campus_id', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, LogsActivity, Notifiable, SoftDeletes;

    protected string $activityModule = 'users';

    /**
     * Role and permission slugs for this request (see access()).
     *
     * @var array{roles: array<int, string>, permissions: array<int, string>}|null
     */
    private ?array $accessList = null;

    /**
     * Flushes seen when $accessList was read; a later change in this process makes it reload.
     */
    private int $accessListFlushes = 0;

    /**
     * Access flushes in this process (a long-lived object notices a change made elsewhere in the same request).
     */
    private static int $flushes = 0;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Find an account by any of its sign-in identifiers: email, username or phone.
     */
    public static function findForLogin(string $identifier): ?self
    {
        $identifier = trim($identifier);

        return static::query()
            ->where('email', $identifier)
            ->orWhere('username', $identifier)
            ->orWhere('phone', $identifier)
            ->first();
    }

    /**
     * Uploaded documents (CNIC, certificates, photos…).
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function ownedTaskBoards(): HasMany
    {
        return $this->hasMany(TaskBoard::class, 'owner_id');
    }

    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    /**
     * The roles that belong to the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->using(RoleUser::class)->withTimestamps();
    }

    /**
     * Check if user has specific role or one of given roles using direct SQL whereIn.
     */
    public function hasRole(string|array $roles): bool
    {
        $roleList = collect(is_array($roles) ? $roles : explode(',', str_replace(' ', '', $roles)))
            ->filter()
            ->values()
            ->all();

        if ($roleList === []) {
            return false;
        }

        return array_intersect($roleList, $this->access()['roles']) !== [];
    }

    /**
     * The account's role and permission slugs, read once per request and cached until any role, permission or
     * role assignment changes (flushAccess bumps the version, so every user's list is rebuilt on next use).
     *
     * @return array{roles: array<int, string>, permissions: array<int, string>}
     */
    public function access(): array
    {
        if ($this->accessList !== null && $this->accessListFlushes === self::$flushes) {
            return $this->accessList;
        }

        $this->accessListFlushes = self::$flushes;

        return $this->accessList = Cache::remember('access:'.(int) Cache::get('access-version', 1).':'.$this->id, now()->addDay(), function (): array {
            $roles = $this->roles()->with('permissions:id,slug')->get(['roles.id', 'roles.slug']);

            return [
                'roles' => $roles->pluck('slug')->values()->all(),
                'permissions' => $roles->flatMap->permissions->pluck('slug')->unique()->values()->all(),
            ];
        });
    }

    /**
     * Forget every cached role / permission list (called on any role, permission or assignment change).
     */
    public static function flushAccess(): void
    {
        self::$flushes++;
        Cache::forever('access-version', (int) Cache::get('access-version', 1) + 1);
    }

    /**
     * Check if user has any of the given roles.
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->hasRole($roles);
    }

    /**
     * Check if user has all of the given roles.
     */
    public function hasAllRoles(array $roles): bool
    {
        return array_diff($roles, $this->access()['roles']) === [];
    }

    /**
     * Check if user has a specific permission or any of given permissions (via assigned roles) using direct SQL.
     */
    public function hasPermission(string|array $permissions): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        $permissionList = collect(is_array($permissions) ? $permissions : explode(',', str_replace(' ', '', $permissions)))
            ->filter()
            ->values()
            ->all();

        if ($permissionList === []) {
            return false;
        }

        return array_intersect($permissionList, $this->access()['permissions']) !== [];
    }

    /**
     * Assign a role to the user by slug, ID, or Role instance.
     */
    public function assignRole(string|int|Role $role): void
    {
        if (is_string($role)) {
            $role = Role::where('slug', $role)->firstOrFail();
        } elseif (is_numeric($role)) {
            $role = Role::findOrFail($role);
        }

        $this->roles()->syncWithoutDetaching([$role->id]);
    }

    /**
     * Remove a role from the user.
     */
    public function removeRole(string|int|Role $role): void
    {
        if (is_string($role)) {
            $role = Role::where('slug', $role)->first();
        } elseif (is_numeric($role)) {
            $role = Role::find($role);
        }

        if ($role) {
            $this->roles()->detach($role->id);
        }
    }

    /**
     * Sync user roles without loops or map using single SQL lookup.
     */
    public function syncRoles(array $roles): void
    {
        $roleIds = Role::whereIn('id', $roles)
            ->orWhereIn('slug', $roles)
            ->pluck('id');

        $this->roles()->sync($roleIds);
    }
}

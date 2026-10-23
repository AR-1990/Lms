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
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'username', 'phone', 'photo_path', 'is_active', 'must_change_password', 'campus_id', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, LogsActivity, Notifiable, SoftDeletes;

    protected string $activityModule = 'users';

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
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps();
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

        return $this->roles()->whereIn('slug', $roleList)->exists();
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
        $count = $this->roles()->whereIn('slug', $roles)->count();

        return $count === count($roles);
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

        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permissionList) {
                $query->whereIn('slug', $permissionList);
            })
            ->exists();
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

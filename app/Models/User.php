<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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
        ];
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
     *
     * @param string|array $roles
     * @return bool
     */
    public function hasRole(string|array $roles): bool
    {
        $roleList = is_array($roles) ? $roles : explode(',', $roles);

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
        $permissionList = is_array($permissions) ? $permissions : explode(',', $permissions);

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

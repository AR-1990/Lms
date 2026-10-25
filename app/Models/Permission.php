<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Permission extends Model
{
    use HasFactory, LogsActivity;

    protected string $activityModule = 'permissions';

    protected $fillable = [
        'name',
        'slug',
        'group',
        'description',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($permission) {
            if (empty($permission->slug)) {
                $permission->slug = Str::slug($permission->name);
            }
        });

        // Renaming or removing a role / permission changes what every holder may do.
        static::saved(fn () => User::flushAccess());
        static::deleted(fn () => User::flushAccess());
    }

    /**
     * The roles that belong to the permission.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'permission_role')->using(PermissionRole::class)->withTimestamps();
    }
}

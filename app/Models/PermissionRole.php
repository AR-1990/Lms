<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A role's permission. Any attach, detach or sync refreshes the cached access lists (User::access).
 */
class PermissionRole extends Pivot
{
    protected $table = 'permission_role';

    protected static function booted(): void
    {
        static::saved(fn () => User::flushAccess());
        static::deleted(fn () => User::flushAccess());
    }
}

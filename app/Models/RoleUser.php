<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A user's role. Any attach, detach or sync refreshes the cached access lists (User::access).
 */
class RoleUser extends Pivot
{
    protected $table = 'role_user';

    protected static function booted(): void
    {
        static::saved(fn () => User::flushAccess());
        static::deleted(fn () => User::flushAccess());
    }
}

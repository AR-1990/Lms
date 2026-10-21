<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A signed-in app or browser of a user (one API token per device, push token for notifications).
 */
#[Fillable(['user_id', 'platform', 'device_name', 'device_id', 'app_version', 'push_token', 'personal_access_token_id', 'last_active_at', 'is_active'])]
#[Hidden(['push_token'])]
class UserDevice extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_active_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Who opened an announcement (and, when asked, acknowledged it) — gives "read by 82%".
 */
#[Fillable(['announcement_id', 'user_id', 'read_at', 'acknowledged_at', 'device'])]
class AnnouncementRead extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'acknowledged_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One line in a user's in-app bell (web and mobile). Plain columns instead of Laravel's JSON notifications table.
 */
#[Fillable(['user_id', 'event_key', 'title', 'body', 'url', 'subject_type', 'subject_id', 'is_urgent', 'read_at'])]
class UserNotification extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_urgent' => 'boolean', 'read_at' => 'datetime'];
    }
}

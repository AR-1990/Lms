<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * "Delete for me": the message is hidden for this person only.
 */
#[Fillable(['message_id', 'user_id', 'created_at'])]
class ChatMessageDeletion extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}

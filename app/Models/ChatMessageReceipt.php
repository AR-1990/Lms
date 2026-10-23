<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Delivered / read ticks of one message for one person.
 */
#[Fillable(['message_id', 'user_id', 'delivered_at', 'read_at'])]
class ChatMessageReceipt extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['delivered_at' => 'datetime', 'read_at' => 'datetime'];
    }
}

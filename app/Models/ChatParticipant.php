<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Membership of a chat. Leaving sets left_at (the row stays, so history is kept).
 */
#[Fillable(['conversation_id', 'user_id', 'role', 'joined_at', 'left_at', 'last_read_message_id', 'muted_until', 'is_pinned', 'is_archived'])]
class ChatParticipant extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['joined_at' => 'datetime', 'left_at' => 'datetime', 'muted_until' => 'datetime', 'is_pinned' => 'boolean', 'is_archived' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }
}

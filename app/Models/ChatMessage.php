<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One chat message. "Delete for everyone" only hides the text (deleted_for_everyone_at); nothing is erased.
 */
#[Fillable(['conversation_id', 'sender_id', 'type', 'body', 'reply_to_message_id', 'forwarded_from_message_id', 'client_uuid', 'edited_at', 'deleted_for_everyone_at'])]
class ChatMessage extends Model
{
    public const TYPES = ['text', 'image', 'file', 'voice', 'system'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['edited_at' => 'datetime', 'deleted_for_everyone_at' => 'datetime'];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'reply_to_message_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(ChatMessageReaction::class, 'message_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(ChatMessageReceipt::class, 'message_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}

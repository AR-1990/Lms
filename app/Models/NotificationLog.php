<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only record of every SMS / email / push / WhatsApp message: queued → sent / failed.
 */
#[Fillable(['channel', 'recipient', 'user_id', 'message_template_id', 'event_key', 'subject', 'body', 'status', 'provider_reference', 'error', 'sent_at'])]
class NotificationLog extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }
}

<?php

namespace App\Jobs;

use App\Models\ChatMessage;
use App\Models\ChatParticipant;
use App\Services\PushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Pushes a new chat message to the phones of everyone else in the chat (muted members are skipped), so a
 * closed app still shows it as soon as the phone is back online.
 */
class SendChatPush implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $messageId) {}

    public function handle(PushService $pushService): void
    {
        $message = ChatMessage::with(['conversation:id,type,name', 'sender:id,name'])->find($this->messageId);

        if ($message === null || ! $pushService->isConfigured()) {
            return;
        }

        $title = $message->conversation->type === 'group' ? "{$message->sender->name} @ {$message->conversation->name}" : $message->sender->name;
        $body = $message->body ?: ($message->type === 'image' ? 'Photo' : 'File');
        $recipients = ChatParticipant::where('conversation_id', $message->conversation_id)
            ->where('user_id', '!=', $message->sender_id)
            ->whereNull('left_at')
            ->where(fn ($query) => $query->whereNull('muted_until')->orWhere('muted_until', '<=', now()))
            ->pluck('user_id');

        foreach ($recipients as $userId) {
            try {
                $pushService->sendToUser($userId, $title, $body, ['type' => 'chat', 'conversation_id' => $message->conversation_id]);
            } catch (Throwable) {
                // One phone failing must not stop the others; the next message tries again.
            }
        }
    }
}

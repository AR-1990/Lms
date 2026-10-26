<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Services\MessagingGatewayService;
use App\Services\PushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends one queued email, push (to all the user's active devices), SMS or WhatsApp message from the
 * delivery log and records the outcome on the same row.
 */
class SendNotificationMessage implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $notificationLogId) {}

    public function handle(PushService $pushService, MessagingGatewayService $gateway): void
    {
        $log = NotificationLog::find($this->notificationLogId);

        if ($log === null || $log->status !== 'queued') {
            return;
        }

        try {
            if ($log->channel === 'push') {
                $sent = $pushService->sendToUser($log->user_id, $log->subject ?? 'School notice', $log->body, ['event_key' => (string) $log->event_key]);
                $log->update($sent > 0 ? ['status' => 'sent', 'sent_at' => now()] : ['status' => 'failed', 'error' => 'The app is no longer installed on any device.']);

                return;
            }

            if (in_array($log->channel, ['sms', 'whatsapp'], true)) {
                $gateway->send($log->channel, $log->recipient, $log->body);
                $log->update(['status' => 'sent', 'sent_at' => now()]);

                return;
            }

            Mail::raw($log->body, fn (Message $message) => $message->to($log->recipient)->subject($log->subject ?? 'School notice'));
            $log->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (Throwable $exception) {
            $log->update(['status' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 500)]);
        }
    }
}

<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends one queued email from the delivery log and records the outcome on the same row.
 */
class SendNotificationMessage implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $notificationLogId) {}

    public function handle(): void
    {
        $log = NotificationLog::find($this->notificationLogId);

        if ($log === null || $log->status !== 'queued') {
            return;
        }

        try {
            Mail::raw($log->body, fn (Message $message) => $message->to($log->recipient)->subject($log->subject ?? 'School notice'));
            $log->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (Throwable $exception) {
            $log->update(['status' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 500)]);
        }
    }
}

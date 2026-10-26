<?php

namespace App\Services;

use App\Events\NotificationSent;
use App\Jobs\SendNotificationMessage;
use App\Models\Guardian;
use App\Models\MessageTemplate;
use App\Models\NotificationLog;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

/**
 * Delivers a message to users: the in-app bell (web + mobile) plus email / SMS / push / WhatsApp rows in
 * the delivery log. Email, push (Firebase; every bell item is also pushed to the user's phones) and SMS /
 * WhatsApp (the provider's HTTP gateway) are sent on the queue once configured; until then a channel's
 * messages are logged as not sent (PLAN.md §27).
 */
class NotificationService
{
    /**
     * @param  array<int, int>  $userIds
     * @param  array<int, string>  $channels  in_app, push, sms, email, whatsapp
     */
    public function notify(array $userIds, string $eventKey, string $title, ?string $body, ?string $url = null, ?Model $subject = null, bool $isUrgent = false, array $channels = ['in_app'], ?int $templateId = null): int
    {
        $userIds = array_values(array_unique(array_filter($userIds)));

        if ($userIds === []) {
            return 0;
        }

        $now = now();
        $channels = in_array('in_app', $channels, true) && app(PushService::class)->isConfigured() ? array_values(array_unique([...$channels, 'push'])) : $channels;

        if (in_array('in_app', $channels, true)) {
            foreach (array_chunk($userIds, 500) as $chunk) {
                UserNotification::insert(array_map(fn (int $userId): array => [
                    'user_id' => $userId, 'event_key' => $eventKey, 'title' => mb_substr($title, 0, 255), 'body' => $body === null ? null : mb_substr($body, 0, 1000),
                    'url' => $url, 'subject_type' => $subject?->getMorphClass(), 'subject_id' => $subject?->getKey(), 'is_urgent' => $isUrgent,
                    'created_at' => $now, 'updated_at' => $now,
                ], $chunk));
            }

            NotificationSent::dispatch($userIds, ['event_key' => $eventKey, 'title' => mb_substr($title, 0, 255), 'body' => $body === null ? null : mb_substr($body, 0, 200), 'url' => $url, 'is_urgent' => $isUrgent]);
        }

        foreach (array_diff($channels, ['in_app']) as $channel) {
            $this->log($channel, $userIds, $eventKey, $title, (string) $body, $templateId);
        }

        return count($userIds);
    }

    /**
     * Send every active template of a system event (absent alert, result published…) with its placeholders filled.
     *
     * @param  array<int, int>  $userIds
     * @param  array<string, string|null>  $placeholders
     */
    public function notifyEvent(string $eventKey, array $userIds, array $placeholders, ?Model $subject = null, ?string $url = null): void
    {
        $placeholders['school_name'] ??= SystemSetting::query()->value('school_name');
        $fill = fn (?string $text): ?string => $text === null ? null : strtr($text, collect($placeholders)->mapWithKeys(fn (?string $value, string $key): array => ['{'.$key.'}' => (string) $value])->all());

        foreach (MessageTemplate::where('event_key', $eventKey)->where('is_active', true)->get() as $template) {
            $this->notify($userIds, $eventKey, $fill($template->subject) ?? MessageTemplate::EVENTS[$eventKey][0], $fill($template->body), $url, $subject, false, [$template->channel], $template->id);
        }
    }

    /**
     * @param  array<int, int>  $userIds
     */
    private function log(string $channel, array $userIds, string $eventKey, string $subject, string $body, ?int $templateId): void
    {
        $users = User::whereIn('id', $userIds)->with('devices:id,user_id,push_token,is_active')->get(['id', 'email', 'phone']);
        $queued = $channel === 'email' || ($channel === 'push' && app(PushService::class)->isConfigured()) || app(MessagingGatewayService::class)->isConfigured($channel);

        foreach ($users as $user) {
            $recipient = match ($channel) {
                'email' => $user->email,
                'push' => $user->devices->where('is_active', true)->whereNotNull('push_token')->isNotEmpty() ? 'devices of user #'.$user->id : null,
                default => $user->phone,
            };

            if (blank($recipient)) {
                continue;
            }

            $log = NotificationLog::create([
                'channel' => $channel, 'recipient' => $recipient, 'user_id' => $user->id, 'message_template_id' => $templateId, 'event_key' => $eventKey,
                'subject' => mb_substr($subject, 0, 255), 'body' => $body,
                'status' => $queued ? 'queued' : 'failed',
                'error' => $queued ? null : 'No '.$channel.' provider is configured yet.',
            ]);

            if ($queued) {
                SendNotificationMessage::dispatch($log->id)->afterCommit();
            }
        }
    }

    /**
     * Portal accounts of these students and of their guardians (who a student notice goes to).
     *
     * @param  iterable<int, int>  $studentIds
     * @return Collection<int, int>
     */
    public function familyUserIds(iterable $studentIds): Collection
    {
        $studentIds = collect($studentIds)->all();

        return Student::whereIn('id', $studentIds)->whereNotNull('user_id')->pluck('user_id')
            ->merge(Guardian::whereNotNull('user_id')->whereIn('id', DB::table('guardian_student')->whereNull('deleted_at')->whereIn('student_id', $studentIds)->select('guardian_id'))->pluck('user_id'))
            ->unique()
            ->values();
    }

    /* ---------- Bell ---------- */

    public function forUser(User $user, bool $unreadOnly = false): Builder
    {
        return UserNotification::where('user_id', $user->id)
            ->when($unreadOnly, fn (Builder $query) => $query->whereNull('read_at'))
            ->latest('id');
    }

    public function unreadCount(User $user): int
    {
        return $this->forUser($user, true)->count();
    }

    /**
     * Mark one notification (or, with null, all of them) as read.
     */
    public function markRead(User $user, ?int $notificationId = null): int
    {
        return $this->forUser($user, true)
            ->when($notificationId, fn (Builder $query, int $id) => $query->whereKey($id))
            ->update(['read_at' => now()]);
    }

    /* ---------- Message templates & delivery log (admin) ---------- */

    /**
     * @return array<string, mixed>
     */
    public function templatesDatatable(): array
    {
        return DataTables::eloquent(MessageTemplate::query())
            ->addColumn('event_label', fn (MessageTemplate $template): string => MessageTemplate::EVENTS[$template->event_key][0] ?? $template->event_key)
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveTemplate(?MessageTemplate $template, array $data): MessageTemplate
    {
        return DB::transaction(function () use ($template, $data): MessageTemplate {
            if ($template === null) {
                return MessageTemplate::create($data);
            }

            $template->update($data);

            return $template;
        });
    }

    public function deleteTemplate(MessageTemplate $template): void
    {
        DB::transaction(fn () => $template->delete());
    }

    /**
     * @param  array{channel?: string|null, status?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function logsDatatable(array $filters): array
    {
        $query = NotificationLog::query()
            ->when($filters['channel'] ?? null, fn (Builder $inner, string $channel) => $inner->where('channel', $channel))
            ->when($filters['status'] ?? null, fn (Builder $inner, string $status) => $inner->where('status', $status));

        return DataTables::eloquent($query)
            ->editColumn('created_at', fn (NotificationLog $log): string => $log->created_at->toIso8601String())
            ->toArray();
    }
}

<?php

namespace App\Services;

use App\Events\ChatEvent;
use App\Models\ChatConversation;
use App\Models\ChatMention;
use App\Models\ChatMessage;
use App\Models\ChatMessageDeletion;
use App\Models\ChatMessageReaction;
use App\Models\ChatMessageReceipt;
use App\Models\ChatParticipant;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

/**
 * Staff chat: direct and group conversations, department groups, messages with replies, files, reactions,
 * mentions, edit (15 min) and delete for me / everyone, delivered and read ticks. Live updates go out as
 * ChatEvent on private channels: chat.{conversation} and App.Models.User.{user}.
 */
class ChatService
{
    public const EDIT_MINUTES = 15;

    public const REACTIONS = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

    public function __construct(
        private AttachmentService $attachmentService,
        private NotificationService $notificationService,
        private ActivityLogger $activityLogger,
    ) {}

    /* ---------- Conversations ---------- */

    /**
     * The user's chat list, pinned first then newest; with the other person (direct) and unread counts.
     *
     * @return Collection<int, ChatConversation>
     */
    public function conversationsFor(User $user, bool $archived = false): Collection
    {
        $mine = ChatParticipant::where('user_id', $user->id)->whereNull('left_at')->where('is_archived', $archived)->get()->keyBy('conversation_id');

        $conversations = ChatConversation::query()
            ->whereIn('id', $mine->keys())
            ->with(['lastMessage.sender:id,name', 'participants' => fn ($query) => $query->whereNull('left_at')->with('user:id,name,last_seen_at')])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();

        $hidden = ChatMessageDeletion::where('user_id', $user->id)->pluck('message_id');

        return $conversations->each(function (ChatConversation $conversation) use ($mine, $user, $hidden): void {
            $me = $mine[$conversation->id];
            $conversation->setAttribute('me', $me);
            $conversation->setAttribute('unread_count', ChatMessage::where('conversation_id', $conversation->id)
                ->where('id', '>', $me->last_read_message_id ?? 0)->where('sender_id', '!=', $user->id)->whereNotIn('id', $hidden)->count());
        })->sortByDesc(fn (ChatConversation $conversation): string => ($conversation->me->is_pinned ? '1' : '0').($conversation->last_message_at?->format('YmdHis') ?? '0'))->values();
    }

    public function find(User $user, int $conversationId): ChatConversation
    {
        $conversation = ChatConversation::with(['participants' => fn ($query) => $query->whereNull('left_at')->with('user:id,name,last_seen_at')])->findOrFail($conversationId);

        abort_unless($conversation->participants->contains('user_id', $user->id), 404);

        return $conversation;
    }

    public function canJoin(User $user, int $conversationId): bool
    {
        // Monitors listen silently: a private channel shows no one who is subscribed.
        return $user->hasPermission('monitor-chat')
            || ($user->hasPermission('use-chat') && ChatParticipant::where('conversation_id', $conversationId)->where('user_id', $user->id)->whereNull('left_at')->exists());
    }

    /**
     * Open (or reuse) the one-to-one chat between two people.
     */
    public function startDirect(User $user, User $other): ChatConversation
    {
        if ($other->id === $user->id || ! $other->hasPermission('use-chat')) {
            throw ValidationException::withMessages(['user_id' => ['Choose a colleague who can use chat.']]);
        }

        $existing = ChatConversation::where('type', 'direct')
            ->whereHas('participants', fn (Builder $query) => $query->where('user_id', $user->id))
            ->whereHas('participants', fn (Builder $query) => $query->where('user_id', $other->id))
            ->first();

        if ($existing !== null) {
            ChatParticipant::where('conversation_id', $existing->id)->whereIn('user_id', [$user->id, $other->id])->update(['left_at' => null, 'is_archived' => false]);

            return $existing;
        }

        return DB::transaction(function () use ($user, $other): ChatConversation {
            $conversation = ChatConversation::create(['type' => 'direct', 'created_by' => $user->id]);

            foreach ([$user->id, $other->id] as $userId) {
                $conversation->participants()->create(['user_id' => $userId, 'role' => 'member', 'joined_at' => now()]);
            }

            return $conversation;
        });
    }

    /**
     * @param  array<int, int>  $memberIds
     */
    public function createGroup(User $user, string $name, ?string $description, array $memberIds, bool $onlyAdminsCanPost): ChatConversation
    {
        return DB::transaction(function () use ($user, $name, $description, $memberIds, $onlyAdminsCanPost): ChatConversation {
            $conversation = ChatConversation::create(['type' => 'group', 'name' => $name, 'description' => $description, 'created_by' => $user->id, 'only_admins_can_post' => $onlyAdminsCanPost]);
            $conversation->participants()->create(['user_id' => $user->id, 'role' => 'admin', 'joined_at' => now()]);

            foreach (array_diff(array_unique($memberIds), [$user->id]) as $memberId) {
                $conversation->participants()->create(['user_id' => $memberId, 'role' => 'member', 'joined_at' => now()]);
            }

            $this->system($conversation, $user, "{$user->name} created the group \"{$name}\"");
            $this->activityLogger->record('chat', 'created', "{$user->name} created chat group \"{$name}\"", $conversation);

            $monitorIds = User::where('is_active', true)->whereKeyNot($user->id)
                ->whereHas('roles.permissions', fn (Builder $query) => $query->where('slug', 'monitor-chat'))->pluck('id')->all();
            $this->notificationService->notify($monitorIds, 'chat_group_created', "{$user->name} created the chat group \"{$name}\"",
                count($conversation->participants()->get(['id'])).' member(s). '.Str::limit((string) $description, 150), route('admin.chat-monitor', ['conversation' => $conversation->id]), $conversation);

            return $conversation;
        });
    }

    /**
     * Group admins rename the group, add or remove people; anyone may leave.
     *
     * @param  array<string, mixed>  $data  name, description, only_admins_can_post, add_ids[], remove_ids[]
     */
    public function updateGroup(User $user, ChatConversation $conversation, array $data): ChatConversation
    {
        $isAdmin = $conversation->participants->firstWhere('user_id', $user->id)?->role === 'admin';

        if ($conversation->type !== 'group' || $conversation->department_id !== null || ! $isAdmin) {
            throw ValidationException::withMessages(['conversation' => ['Only a group admin can change this group. Department groups follow HR.']]);
        }

        DB::transaction(function () use ($user, $conversation, $data): void {
            $conversation->update(collect($data)->only(['name', 'description', 'only_admins_can_post'])->all());

            foreach (array_unique($data['add_ids'] ?? []) as $userId) {
                ChatParticipant::updateOrCreate(['conversation_id' => $conversation->id, 'user_id' => $userId], ['left_at' => null, 'joined_at' => now(), 'role' => 'member']);
                $this->system($conversation, $user, $user->name.' added '.User::whereKey($userId)->value('name'));
            }

            foreach (array_diff($data['remove_ids'] ?? [], [$user->id]) as $userId) {
                ChatParticipant::where('conversation_id', $conversation->id)->where('user_id', $userId)->update(['left_at' => now()]);
                $this->system($conversation, $user, $user->name.' removed '.User::whereKey($userId)->value('name'));
            }
        });

        return $this->find($user, $conversation->id);
    }

    public function leave(User $user, ChatConversation $conversation): void
    {
        if ($conversation->type !== 'group' || $conversation->department_id !== null) {
            throw ValidationException::withMessages(['conversation' => ['You can only leave groups you joined yourself.']]);
        }

        DB::transaction(function () use ($user, $conversation): void {
            ChatParticipant::where('conversation_id', $conversation->id)->where('user_id', $user->id)->update(['left_at' => now()]);
            $this->system($conversation, $user, "{$user->name} left");
        });
    }

    /**
     * @param  array{is_pinned?: bool, is_archived?: bool, muted?: bool}  $settings
     */
    public function setPreferences(User $user, ChatConversation $conversation, array $settings): ChatParticipant
    {
        $me = ChatParticipant::where('conversation_id', $conversation->id)->where('user_id', $user->id)->firstOrFail();
        $me->update([
            ...collect($settings)->only(['is_pinned', 'is_archived'])->all(),
            ...(array_key_exists('muted', $settings) ? ['muted_until' => $settings['muted'] ? now()->addYears(10) : null] : []),
        ]);

        return $me;
    }

    /**
     * One group per active department with its current staff (run nightly and after seeding).
     */
    public function syncDepartmentGroups(): int
    {
        $owner = User::whereHas('roles', fn (Builder $query) => $query->where('slug', 'admin'))->value('id');

        foreach (Department::where('is_active', true)->get(['id', 'name']) as $department) {
            $conversation = ChatConversation::firstOrCreate(['type' => 'group', 'department_id' => $department->id], ['name' => $department->name, 'created_by' => $owner, 'description' => 'Department group (automatic)']);
            $staff = Employee::where('department_id', $department->id)->whereIn('status', Employee::CURRENT_STATUSES)->whereNotNull('user_id')->pluck('user_id')->all();

            ChatParticipant::where('conversation_id', $conversation->id)->whereNotIn('user_id', $staff)->whereNull('left_at')->update(['left_at' => now()]);

            foreach ($staff as $userId) {
                ChatParticipant::updateOrCreate(['conversation_id' => $conversation->id, 'user_id' => $userId], ['left_at' => null, 'role' => 'member', 'joined_at' => now()]);
            }
        }

        return ChatConversation::whereNotNull('department_id')->count();
    }

    /* ---------- Oversight (monitor-chat) ---------- */

    /**
     * Every direct and group chat for the oversight screen; filter by type or by a person in it.
     *
     * @param  array{type?: string|null, user_id?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function monitorDatatable(array $filters): array
    {
        $query = ChatConversation::query()
            ->select(['id', 'type', 'name', 'department_id', 'created_by', 'last_message_at', 'created_at'])
            ->with(['creator:id,name', 'participants' => fn ($query) => $query->whereNull('left_at')->select(['id', 'conversation_id', 'user_id'])->with('user:id,name')])
            ->withCount('messages')
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['user_id'] ?? null, fn (Builder $query, int $userId) => $query->whereHas('participants', fn (Builder $inner) => $inner->where('user_id', $userId)));

        return DataTables::eloquent($query)
            ->addColumn('title', fn (ChatConversation $conversation): string => $conversation->type === 'direct' ? $conversation->participants->pluck('user.name')->implode(' ↔ ') : (string) $conversation->name)
            ->addColumn('members', fn (ChatConversation $conversation): int => $conversation->participants->count())
            ->addColumn('creator_name', fn (ChatConversation $conversation): ?string => $conversation->creator?->name)
            ->addColumn('is_department', fn (ChatConversation $conversation): bool => $conversation->department_id !== null)
            ->editColumn('last_message_at', fn (ChatConversation $conversation): ?string => $conversation->last_message_at?->toIso8601String())
            ->filterColumn('title', fn (Builder $query, string $term) => $query->where(fn (Builder $inner) => $inner->where('name', 'like', "%{$term}%")
                ->orWhereHas('participants.user', fn (Builder $people) => $people->where('name', 'like', "%{$term}%"))))
            ->removeColumn('participants', 'creator')
            // Plain text for every client; the web grid escapes it when drawing.
            ->rawColumns(['title', 'creator_name'])
            ->toArray();
    }

    /**
     * Open any chat as a monitor (logged for the admin only). The monitor is not added to it.
     */
    public function monitorFind(User $monitor, int $conversationId): ChatConversation
    {
        $conversation = ChatConversation::with(['creator:id,name', 'participants' => fn ($query) => $query->whereNull('left_at')->with('user:id,name,last_seen_at')])->findOrFail($conversationId);
        $this->activityLogger->record('chat', 'viewed', "{$monitor->name} reviewed the chat \"".($conversation->name ?? $conversation->participants->pluck('user.name')->implode(' ↔ ')).'"', $conversation);

        return $conversation;
    }

    /**
     * Messages for a monitor: no ticks, no "last seen", and messages deleted by staff are still shown.
     *
     * @return array<int, array<string, mixed>>
     */
    public function monitorMessages(ChatConversation $conversation, ?int $beforeId = null, int $limit = 60): array
    {
        return ChatMessage::where('conversation_id', $conversation->id)
            ->when($beforeId, fn (Builder $query, int $id) => $query->where('id', '<', $id))
            ->with(['sender:id,name', 'replyTo.sender:id,name', 'reactions', 'receipts', 'attachments'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(fn (ChatMessage $message): array => [...$this->present($message), 'body' => $message->body])
            ->values()
            ->all();
    }

    /**
     * Add or remove people in any staff group and choose its group admins (department groups follow HR).
     *
     * @param  array<int, int>  $addIds
     * @param  array<int, int>  $removeIds
     * @param  array<int, int>  $makeAdminIds
     * @param  array<int, int>  $makeMemberIds
     */
    public function monitorMembers(User $monitor, ChatConversation $conversation, array $addIds, array $removeIds, array $makeAdminIds = [], array $makeMemberIds = []): ChatConversation
    {
        if ($conversation->type !== 'group' || $conversation->department_id !== null) {
            throw ValidationException::withMessages(['conversation' => ['Members can be changed only in staff groups. Department groups follow the HR department.']]);
        }

        DB::transaction(function () use ($monitor, $conversation, $addIds, $removeIds, $makeAdminIds, $makeMemberIds): void {
            foreach (array_unique($addIds) as $userId) {
                ChatParticipant::updateOrCreate(['conversation_id' => $conversation->id, 'user_id' => $userId], ['left_at' => null, 'joined_at' => now(), 'role' => 'member']);
                $this->system($conversation, $monitor, $monitor->name.' added '.User::whereKey($userId)->value('name'));
            }

            foreach (array_unique($removeIds) as $userId) {
                ChatParticipant::where('conversation_id', $conversation->id)->where('user_id', $userId)->whereNull('left_at')->update(['left_at' => now()]);
                $this->system($conversation, $monitor, $monitor->name.' removed '.User::whereKey($userId)->value('name'));
            }

            foreach (['admin' => $makeAdminIds, 'member' => $makeMemberIds] as $role => $userIds) {
                foreach (array_unique($userIds) as $userId) {
                    $changed = ChatParticipant::where('conversation_id', $conversation->id)->where('user_id', $userId)->whereNull('left_at')->where('role', '!=', $role)->update(['role' => $role]);

                    if ($changed > 0) {
                        $name = User::whereKey($userId)->value('name');
                        $this->system($conversation, $monitor, $role === 'admin' ? "{$monitor->name} made {$name} a group admin" : "{$monitor->name} removed {$name} as group admin");
                    }
                }
            }

            $this->activityLogger->record('chat', 'updated', "{$monitor->name} changed the members of \"{$conversation->name}\"", $conversation);
        });

        $userChannels = ChatParticipant::where('conversation_id', $conversation->id)->pluck('user_id')->map(fn ($id): string => 'App.Models.User.'.$id)->all();
        ChatEvent::dispatch($userChannels, 'conversation.updated', ['conversation_id' => $conversation->id]);

        return ChatConversation::with(['creator:id,name', 'participants' => fn ($query) => $query->whereNull('left_at')->with('user:id,name,last_seen_at')])->findOrFail($conversation->id);
    }

    /* ---------- Messages ---------- */

    /**
     * Newest page of messages (or older than $beforeId), oldest first. Opening the chat marks them delivered.
     *
     * @return array<int, array<string, mixed>>
     */
    public function messages(User $user, ChatConversation $conversation, ?int $beforeId = null, int $limit = 30): array
    {
        $messages = ChatMessage::where('conversation_id', $conversation->id)
            ->whereNotIn('id', ChatMessageDeletion::where('user_id', $user->id)->select('message_id'))
            ->when($beforeId, fn (Builder $query, int $id) => $query->where('id', '<', $id))
            ->with(['sender:id,name', 'replyTo.sender:id,name', 'reactions', 'receipts', 'attachments'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        ChatMessageReceipt::whereIn('message_id', $messages->pluck('id'))->where('user_id', $user->id)->whereNull('delivered_at')->update(['delivered_at' => now()]);
        $user->forceFill(['last_seen_at' => now()])->saveQuietly();

        return $messages->map(fn (ChatMessage $message): array => $this->present($message))->all();
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @param  array<int, int>  $mentionIds
     */
    public function send(User $user, ChatConversation $conversation, ?string $body, ?int $replyToId, ?string $clientUuid, array $files = [], array $mentionIds = [], ?int $forwardedFromId = null): ChatMessage
    {
        $me = $conversation->participants->firstWhere('user_id', $user->id);

        if ($conversation->only_admins_can_post && $me?->role !== 'admin') {
            throw ValidationException::withMessages(['body' => ['Only group admins can post here.']]);
        }

        if ($clientUuid !== null && ($duplicate = ChatMessage::where('conversation_id', $conversation->id)->where('client_uuid', $clientUuid)->first()) !== null) {
            return $duplicate;
        }

        if ($replyToId !== null && ChatMessage::where('conversation_id', $conversation->id)->whereKey($replyToId)->doesntExist()) {
            throw ValidationException::withMessages(['reply_to_message_id' => ['You can only reply to a message in this chat.']]);
        }

        $message = DB::transaction(function () use ($user, $conversation, $body, $replyToId, $clientUuid, $files, $mentionIds, $forwardedFromId): ChatMessage {
            $type = $files === [] ? 'text' : (collect($files)->every(fn (UploadedFile $file): bool => str_starts_with((string) $file->getMimeType(), 'image/')) ? 'image' : 'file');
            $message = ChatMessage::create([
                'conversation_id' => $conversation->id, 'sender_id' => $user->id, 'type' => $type, 'body' => $body,
                'reply_to_message_id' => $replyToId, 'forwarded_from_message_id' => $forwardedFromId, 'client_uuid' => $clientUuid,
            ]);

            foreach ($files as $file) {
                $this->attachmentService->store($message, 'chat_messages', $file, ['title' => $file->getClientOriginalName()]);
            }

            $others = $conversation->participants->where('user_id', '!=', $user->id)->pluck('user_id');

            foreach ($others as $userId) {
                ChatMessageReceipt::create(['message_id' => $message->id, 'user_id' => $userId]);
            }

            foreach (array_intersect(array_unique($mentionIds), $others->all()) as $userId) {
                ChatMention::create(['message_id' => $message->id, 'user_id' => $userId]);
            }

            $conversation->update(['last_message_id' => $message->id, 'last_message_at' => $message->created_at]);
            ChatParticipant::where('conversation_id', $conversation->id)->where('user_id', $user->id)->update(['last_read_message_id' => $message->id]);
            ChatParticipant::where('conversation_id', $conversation->id)->update(['is_archived' => false]);

            $mentioned = array_values(array_intersect(array_unique($mentionIds), $others->all()));
            $this->notificationService->notify($mentioned, 'chat_mention', "{$user->name} mentioned you in ".($conversation->name ?? 'a chat'), Str::limit((string) $body, 200), route('chat', ['conversation' => $conversation->id]), $message);

            return $message;
        });

        $user->forceFill(['last_seen_at' => now()])->saveQuietly();
        $this->broadcastMessage($conversation, $message, 'message.sent');

        return $message;
    }

    public function edit(User $user, ChatMessage $message, string $body): ChatMessage
    {
        if ($message->sender_id !== $user->id || $message->deleted_for_everyone_at !== null || $message->created_at->diffInMinutes(now()) > self::EDIT_MINUTES) {
            throw ValidationException::withMessages(['body' => ['You can edit your own message for '.self::EDIT_MINUTES.' minutes.']]);
        }

        $message->update(['body' => $body, 'edited_at' => now()]);
        $this->broadcastMessage(ChatConversation::findOrFail($message->conversation_id), $message, 'message.updated');

        return $message;
    }

    /**
     * For me: hidden only for this user. For everyone (own messages): the text is hidden for all; the row stays.
     */
    public function delete(User $user, ChatMessage $message, bool $forEveryone): void
    {
        if (! $forEveryone) {
            ChatMessageDeletion::firstOrCreate(['message_id' => $message->id, 'user_id' => $user->id], ['created_at' => now()]);

            return;
        }

        if ($message->sender_id !== $user->id) {
            throw ValidationException::withMessages(['message' => ['You can delete only your own messages for everyone.']]);
        }

        $message->update(['deleted_for_everyone_at' => now()]);
        $this->activityLogger->record('chat', 'deleted', "{$user->name} deleted a chat message for everyone", $message);
        $this->broadcastMessage(ChatConversation::findOrFail($message->conversation_id), $message, 'message.updated');
    }

    /**
     * Set or change (emoji) or take back (null) the user's reaction.
     */
    public function react(User $user, ChatMessage $message, ?string $emoji): ChatMessage
    {
        ChatMessageReaction::updateOrCreate(['message_id' => $message->id, 'user_id' => $user->id], ['emoji' => $emoji ?? '']);
        $this->broadcastMessage(ChatConversation::findOrFail($message->conversation_id), $message, 'message.updated');

        return $message;
    }

    /**
     * Mark everything up to a message as read: blue ticks for the senders.
     */
    public function markRead(User $user, ChatConversation $conversation, int $upToId): void
    {
        DB::transaction(function () use ($user, $conversation, $upToId): void {
            ChatParticipant::where('conversation_id', $conversation->id)->where('user_id', $user->id)
                ->where(fn (Builder $query) => $query->whereNull('last_read_message_id')->orWhere('last_read_message_id', '<', $upToId))
                ->update(['last_read_message_id' => $upToId]);

            ChatMessageReceipt::where('user_id', $user->id)->whereNull('read_at')
                ->whereIn('message_id', ChatMessage::where('conversation_id', $conversation->id)->where('id', '<=', $upToId)->select('id'))
                ->update(['read_at' => now(), 'delivered_at' => DB::raw('coalesce(delivered_at, CURRENT_TIMESTAMP)')]);
        });

        ChatEvent::dispatch(['chat.'.$conversation->id], 'messages.read', ['conversation_id' => $conversation->id, 'user_id' => $user->id, 'up_to_id' => $upToId]);
    }

    /**
     * @param  array<int, int>  $conversationIds
     */
    public function forward(User $user, ChatMessage $message, array $conversationIds): int
    {
        foreach ($conversationIds as $conversationId) {
            $target = $this->find($user, $conversationId);
            $this->send($user, $target, $message->deleted_for_everyone_at ? null : $message->body, null, null, [], [], $message->id);
        }

        return count($conversationIds);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(User $user, string $term): array
    {
        $conversationIds = ChatParticipant::where('user_id', $user->id)->whereNull('left_at')->pluck('conversation_id');

        return ChatMessage::whereIn('conversation_id', $conversationIds)
            ->whereNull('deleted_for_everyone_at')
            ->whereNotIn('id', ChatMessageDeletion::where('user_id', $user->id)->select('message_id'))
            ->where('body', 'like', '%'.$term.'%')
            ->with('sender:id,name')
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn (ChatMessage $message): array => $this->present($message))
            ->all();
    }

    /**
     * The same shape for API lists and live events. Ticks: sent → delivered (all received) → read (all read).
     *
     * @return array<string, mixed>
     */
    public function present(ChatMessage $message): array
    {
        $deleted = $message->deleted_for_everyone_at !== null;
        $receipts = $message->relationLoaded('receipts') ? $message->receipts : $message->receipts()->get();
        $reactions = ($message->relationLoaded('reactions') ? $message->reactions : $message->reactions()->get())->where('emoji', '!=', '');

        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_id' => $message->sender_id,
            'sender_name' => $message->sender?->name,
            'type' => $message->type,
            'body' => $deleted ? null : $message->body,
            'is_deleted' => $deleted,
            'is_edited' => $message->edited_at !== null,
            'is_forwarded' => $message->forwarded_from_message_id !== null,
            'client_uuid' => $message->client_uuid,
            'reply_to' => $message->replyTo ? [
                'id' => $message->replyTo->id,
                'sender_name' => $message->replyTo->sender?->name,
                'body' => $message->replyTo->deleted_for_everyone_at ? null : Str::limit((string) $message->replyTo->body, 120),
            ] : null,
            'reactions' => $reactions->groupBy('emoji')->map(fn ($group, string $emoji): array => ['emoji' => $emoji, 'user_ids' => $group->pluck('user_id')->values()->all()])->values()->all(),
            'attachments' => $deleted ? [] : ($message->relationLoaded('attachments') ? $message->attachments : $message->attachments()->get())->map(fn ($attachment): array => [
                'id' => $attachment->id, 'name' => $attachment->original_name, 'mime_type' => $attachment->mime_type, 'size_bytes' => $attachment->size_bytes,
                'url' => $this->attachmentService->temporaryUrl($attachment, 60),
            ])->all(),
            'status' => $receipts->isEmpty() ? 'sent' : ($receipts->whereNull('read_at')->isEmpty() ? 'read' : ($receipts->whereNull('delivered_at')->isEmpty() ? 'delivered' : 'sent')),
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    private function system(ChatConversation $conversation, User $user, string $text): void
    {
        $message = ChatMessage::create(['conversation_id' => $conversation->id, 'sender_id' => $user->id, 'type' => 'system', 'body' => $text]);
        $conversation->update(['last_message_id' => $message->id, 'last_message_at' => $message->created_at]);
    }

    private function broadcastMessage(ChatConversation $conversation, ChatMessage $message, string $name): void
    {
        $message->load(['sender:id,name', 'replyTo.sender:id,name', 'reactions', 'receipts', 'attachments']);
        $members = ChatParticipant::where('conversation_id', $conversation->id)->whereNull('left_at')->get(['user_id', 'muted_until']);
        $update = ['conversation_id' => $conversation->id, 'message_id' => $message->id, 'sender_id' => $message->sender_id, 'is_new' => $name === 'message.sent' && $message->type !== 'system'];

        ChatEvent::dispatch(['chat.'.$conversation->id], $name, $this->present($message));

        // Muted members still get the update for their list, but without a sound.
        foreach ($members->groupBy(fn (ChatParticipant $member): string => $member->muted_until?->isFuture() ? 'muted' : 'loud') as $group => $people) {
            ChatEvent::dispatch($people->map(fn (ChatParticipant $member): string => 'App.Models.User.'.$member->user_id)->all(), 'conversation.updated', [...$update, 'is_muted' => $group === 'muted']);
        }
    }
}

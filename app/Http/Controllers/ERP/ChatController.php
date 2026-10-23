<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\ChatService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Staff chat API (permission use-chat), shared by the web chat page and the mobile apps.
 */
class ChatController extends Controller
{
    use ApiResponse;

    public function __construct(private ChatService $chatService) {}

    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user();
        $list = $this->chatService->conversationsFor($user, $request->boolean('archived'));

        return $this->successResponse($list->map(fn (ChatConversation $conversation): array => $this->conversation($conversation, $user))->all(), 'Chats retrieved.');
    }

    public function unread(Request $request): JsonResponse
    {
        return $this->successResponse(['unread' => (int) $this->chatService->conversationsFor($request->user())->sum('unread_count')], 'Unread messages.');
    }

    /**
     * Colleagues who can chat, with when they were last seen.
     */
    public function people(Request $request): JsonResponse
    {
        $people = User::where('is_active', true)->whereKeyNot($request->user()->id)
            ->whereHas('roles.permissions', fn ($query) => $query->where('slug', 'use-chat'))
            ->orderBy('name')->get(['id', 'name', 'last_seen_at']);

        return $this->successResponse($people, 'Colleagues retrieved.');
    }

    public function direct(Request $request): JsonResponse
    {
        $other = User::findOrFail($request->validate(['user_id' => ['required', 'integer']])['user_id']);
        $conversation = $this->chatService->startDirect($request->user(), $other);

        return $this->successResponse($this->conversation($this->chatService->find($request->user(), $conversation->id), $request->user()), 'Chat opened.', 201);
    }

    public function group(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'member_ids' => ['required', 'array', 'min:1', 'max:300'],
            'member_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'only_admins_can_post' => ['sometimes', 'boolean'],
        ]);
        $conversation = $this->chatService->createGroup($request->user(), $validated['name'], $validated['description'] ?? null, $validated['member_ids'], (bool) ($validated['only_admins_can_post'] ?? false));

        return $this->successResponse($this->conversation($this->chatService->find($request->user(), $conversation->id), $request->user()), 'Group created.', 201);
    }

    public function updateGroup(Request $request, int $conversation): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'only_admins_can_post' => ['sometimes', 'boolean'],
            'add_ids' => ['sometimes', 'array', 'max:300'],
            'add_ids.*' => ['integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'remove_ids' => ['sometimes', 'array', 'max:300'],
            'remove_ids.*' => ['integer'],
        ]);
        $record = $this->chatService->updateGroup($request->user(), $this->chatService->find($request->user(), $conversation), $validated);

        return $this->successResponse($this->conversation($record, $request->user()), 'Group updated.');
    }

    public function leave(Request $request, int $conversation): JsonResponse
    {
        $this->chatService->leave($request->user(), $this->chatService->find($request->user(), $conversation));

        return $this->successResponse(null, 'You left the group.');
    }

    public function preferences(Request $request, int $conversation): JsonResponse
    {
        $settings = $request->validate(['is_pinned' => ['sometimes', 'boolean'], 'is_archived' => ['sometimes', 'boolean'], 'muted' => ['sometimes', 'boolean']]);
        $me = $this->chatService->setPreferences($request->user(), $this->chatService->find($request->user(), $conversation), $settings);

        return $this->successResponse($me->only(['is_pinned', 'is_archived', 'muted_until']), 'Saved.');
    }

    public function messages(Request $request, int $conversation): JsonResponse
    {
        $record = $this->chatService->find($request->user(), $conversation);

        return $this->successResponse($this->chatService->messages($request->user(), $record, $request->integer('before') ?: null), 'Messages retrieved.');
    }

    public function send(Request $request, int $conversation): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['nullable', 'required_without:files', 'string', 'max:5000'],
            'reply_to_message_id' => ['nullable', 'integer'],
            'client_uuid' => ['nullable', 'uuid'],
            'files' => ['sometimes', 'array', 'max:5'],
            'files.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,mp3,m4a,ogg,webm'],
            'mention_ids' => ['sometimes', 'array', 'max:50'],
            'mention_ids.*' => ['integer'],
        ]);
        $message = $this->chatService->send(
            $request->user(), $this->chatService->find($request->user(), $conversation), $validated['body'] ?? null, $validated['reply_to_message_id'] ?? null,
            $validated['client_uuid'] ?? null, $request->file('files', []), array_map('intval', $validated['mention_ids'] ?? []),
        );

        return $this->successResponse($this->chatService->present($message->fresh(['sender:id,name', 'replyTo.sender:id,name', 'reactions', 'receipts', 'attachments'])), 'Sent.', 201);
    }

    public function edit(Request $request, int $message): JsonResponse
    {
        $body = $request->validate(['body' => ['required', 'string', 'max:5000']])['body'];
        $record = $this->chatService->edit($request->user(), $this->message($request, $message), $body);

        return $this->successResponse($this->chatService->present($record), 'Edited.');
    }

    public function destroy(Request $request, int $message): JsonResponse
    {
        $forEveryone = $request->validate(['for_everyone' => ['sometimes', 'boolean']])['for_everyone'] ?? false;
        $this->chatService->delete($request->user(), $this->message($request, $message), (bool) $forEveryone);

        return $this->successResponse(null, $forEveryone ? 'Deleted for everyone.' : 'Deleted for you.');
    }

    public function react(Request $request, int $message): JsonResponse
    {
        $emoji = $request->validate(['emoji' => ['nullable', Rule::in(ChatService::REACTIONS)]])['emoji'] ?? null;

        return $this->successResponse($this->chatService->present($this->chatService->react($request->user(), $this->message($request, $message), $emoji)), 'Reaction saved.');
    }

    public function forward(Request $request, int $message): JsonResponse
    {
        $ids = $request->validate(['conversation_ids' => ['required', 'array', 'min:1', 'max:10'], 'conversation_ids.*' => ['integer', 'distinct']])['conversation_ids'];
        $count = $this->chatService->forward($request->user(), $this->message($request, $message), $ids);

        return $this->successResponse(['forwarded' => $count], 'Forwarded.');
    }

    public function read(Request $request, int $conversation): JsonResponse
    {
        $upTo = $request->validate(['up_to_id' => ['required', 'integer']])['up_to_id'];
        $this->chatService->markRead($request->user(), $this->chatService->find($request->user(), $conversation), $upTo);

        return $this->successResponse(null, 'Read.');
    }

    public function search(Request $request): JsonResponse
    {
        $term = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']])['q'];

        return $this->successResponse($this->chatService->search($request->user(), $term), 'Messages found.');
    }

    private function message(Request $request, int $id): ChatMessage
    {
        $message = ChatMessage::findOrFail($id);
        $this->chatService->find($request->user(), $message->conversation_id);

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    private function conversation(ChatConversation $conversation, User $user): array
    {
        $me = $conversation->getAttribute('me') ?? $conversation->participants->firstWhere('user_id', $user->id);
        $other = $conversation->type === 'direct' ? $conversation->participants->firstWhere('user_id', '!=', $user->id)?->user : null;
        $last = $conversation->lastMessage;

        return [
            'id' => $conversation->id,
            'type' => $conversation->type,
            'name' => $other?->name ?? $conversation->name,
            'description' => $conversation->description,
            'is_department' => $conversation->department_id !== null,
            'other_user' => $other ? ['id' => $other->id, 'name' => $other->name, 'last_seen_at' => $other->last_seen_at?->toIso8601String()] : null,
            'members' => $conversation->type === 'group' ? $conversation->participants->map(fn ($participant): array => ['id' => $participant->user_id, 'name' => $participant->user?->name, 'role' => $participant->role])->values()->all() : [],
            'my_role' => $me?->role,
            'only_admins_can_post' => $conversation->only_admins_can_post,
            'is_pinned' => (bool) $me?->is_pinned,
            'is_archived' => (bool) $me?->is_archived,
            'is_muted' => $me?->muted_until?->isFuture() ?? false,
            'unread_count' => (int) ($conversation->getAttribute('unread_count') ?? 0),
            'last_message' => $last ? [
                'id' => $last->id,
                'sender_name' => $last->sender?->name,
                'body' => $last->deleted_for_everyone_at ? 'This message was deleted' : Str::limit((string) ($last->body ?: ($last->type === 'image' ? '📷 Photo' : '📎 File')), 80),
                'created_at' => $last->created_at?->toIso8601String(),
            ] : null,
        ];
    }
}

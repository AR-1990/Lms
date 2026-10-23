<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatParticipant;
use App\Services\ChatService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Chat oversight (permission monitor-chat): read any staff chat without being seen and manage group members.
 */
class ChatMonitorController extends Controller
{
    use ApiResponse;

    public function __construct(private ChatService $chatService) {}

    public function datatable(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::in(['direct', 'group'])],
            'user_id' => ['nullable', 'integer'],
        ]);

        return $this->successResponse($this->chatService->monitorDatatable($filters), 'Chats retrieved.');
    }

    public function show(Request $request, int $conversation): JsonResponse
    {
        return $this->successResponse($this->present($this->chatService->monitorFind($request->user(), $conversation)), 'Chat retrieved.');
    }

    public function messages(Request $request, ChatConversation $conversation): JsonResponse
    {
        $beforeId = $request->validate(['before_id' => ['nullable', 'integer']])['before_id'] ?? null;

        return $this->successResponse($this->chatService->monitorMessages($conversation, $beforeId), 'Messages retrieved.');
    }

    public function members(Request $request, ChatConversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'add_ids' => ['array'],
            'add_ids.*' => ['integer', Rule::exists('users', 'id')->where('is_active', true)],
            'remove_ids' => ['array'],
            'remove_ids.*' => ['integer'],
            'make_admin_ids' => ['array'],
            'make_admin_ids.*' => ['integer'],
            'make_member_ids' => ['array'],
            'make_member_ids.*' => ['integer'],
        ]);
        $updated = $this->chatService->monitorMembers($request->user(), $conversation, $validated['add_ids'] ?? [], $validated['remove_ids'] ?? [], $validated['make_admin_ids'] ?? [], $validated['make_member_ids'] ?? []);

        return $this->successResponse($this->present($updated), 'Group members updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ChatConversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'type' => $conversation->type,
            'name' => $conversation->name,
            'description' => $conversation->description,
            'is_department' => $conversation->department_id !== null,
            'creator_name' => $conversation->creator?->name,
            'created_at' => $conversation->created_at?->toIso8601String(),
            'participants' => $conversation->participants->map(fn (ChatParticipant $participant): array => [
                'user_id' => $participant->user_id,
                'name' => $participant->user?->name,
                'role' => $participant->role,
            ])->values()->all(),
        ];
    }
}

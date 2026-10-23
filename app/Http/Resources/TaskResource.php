<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A task card (Kanban / lists) or, with relations loaded, the full task.
 *
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->only(['id', 'task_no', 'task_board_id', 'parent_task_id', 'title', 'description', 'task_status_id', 'priority_id', 'category_id', 'estimated_minutes', 'spent_minutes', 'recurrence', 'sort_order']),
            'start_at' => $this->start_at?->toIso8601String(),
            'due_at' => $this->due_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'recurrence_until' => $this->recurrence_until?->toDateString(),
            'is_overdue' => $this->completed_at === null && $this->due_at?->isPast() === true,
            'status' => $this->whenLoaded('status', fn (): ?array => $this->status?->only(['id', 'name', 'color', 'is_done'])),
            'priority' => $this->whenLoaded('priority', fn (): ?string => $this->priority?->name),
            'category' => $this->whenLoaded('category', fn (): ?string => $this->category?->name),
            'board' => $this->whenLoaded('board', fn (): ?array => $this->board?->only(['id', 'name'])),
            'created_by_name' => $this->whenLoaded('creator', fn (): ?string => $this->creator?->name),
            'assignees' => $this->whenLoaded('assignees', fn (): array => $this->assignees->map(fn ($assignee): array => ['user_id' => $assignee->user_id, 'name' => $assignee->user?->name])->all()),
            'watchers' => $this->whenLoaded('watchers', fn (): array => $this->watchers->map(fn ($watcher): array => ['user_id' => $watcher->user_id, 'name' => $watcher->user?->name])->all()),
            'checklist' => $this->whenLoaded('checklist', fn (): array => $this->checklist->map(fn ($item): array => $item->only(['id', 'title', 'is_done']))->all()),
            'comments' => $this->whenLoaded('comments', fn (): array => $this->comments->map(fn ($comment): array => [
                'id' => $comment->id, 'user_id' => $comment->user_id, 'user_name' => $comment->user?->name, 'body' => $comment->body, 'created_at' => $comment->created_at?->toIso8601String(),
            ])->all()),
            'subtasks' => $this->whenLoaded('subtasks', fn (): array => $this->subtasks->map(fn ($subtask): array => $subtask->only(['id', 'task_no', 'title']) + ['done' => $subtask->completed_at !== null])->all()),
            'checklist_count' => $this->whenCounted('checklist'),
            'checklist_done_count' => $this->whenCounted('checklist_done_count', fn () => $this->checklist_done_count),
            'comments_count' => $this->whenCounted('comments'),
        ];
    }
}

<?php

namespace App\Services;

use App\Events\TaskChanged;
use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaskChecklistItem;
use App\Models\TaskComment;
use App\Models\TaskCommentMention;
use App\Models\TaskReminder;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

/**
 * Task boards (Kanban), personal tasks, assignees, checklists, comments with @mentions, due reminders and
 * recurring tasks. Every change is logged and broadcast so open boards update live.
 */
class TaskService
{
    public function __construct(
        private NumberSequenceService $numberSequenceService,
        private NotificationService $notificationService,
        private ActivityLogger $activityLogger,
    ) {}

    /* ---------- Boards ---------- */

    /**
     * Boards the user can open: own, member of, their department's, or open to everyone.
     */
    public function boardsFor(User $user): Builder
    {
        $departmentId = Employee::where('user_id', $user->id)->value('department_id');

        return TaskBoard::query()
            ->with(['owner:id,name', 'department:id,name'])
            ->withCount(['tasks', 'tasks as open_tasks_count' => fn (Builder $query) => $query->whereNull('completed_at')])
            ->where('is_archived', false)
            ->when(! $user->hasRole('admin'), fn (Builder $query) => $query->where(function (Builder $inner) use ($user, $departmentId): void {
                $inner->where('owner_id', $user->id)
                    ->orWhere('visibility', 'everyone')
                    ->orWhereHas('members', fn (Builder $member) => $member->where('user_id', $user->id))
                    ->orWhere(fn (Builder $department) => $department->where('visibility', 'department')->whereNotNull('department_id')->where('department_id', $departmentId));
            }))
            ->orderBy('name');
    }

    public function canView(User $user, TaskBoard $board): bool
    {
        return $this->boardsFor($user)->whereKey($board->id)->exists();
    }

    /**
     * Owner, editors and administrators may change a board's tasks; viewers only look.
     */
    public function canEdit(User $user, TaskBoard $board): bool
    {
        return $user->hasRole('admin') || $board->owner_id === $user->id
            || $board->members()->where('user_id', $user->id)->where('role', 'editor')->exists()
            || ($board->visibility !== 'private' && $this->canView($user, $board) && $board->members()->doesntExist());
    }

    /**
     * @param  array<string, mixed>  $data  board fields + members[{user_id, role}]
     */
    public function saveBoard(User $user, ?TaskBoard $board, array $data): TaskBoard
    {
        if ($board !== null && ! $this->canEdit($user, $board)) {
            throw ValidationException::withMessages(['board' => ['Only the owner or an editor can change this board.']]);
        }

        return DB::transaction(function () use ($user, $board, $data): TaskBoard {
            $fields = collect($data)->except('members')->all();

            if ($board === null) {
                $board = TaskBoard::create([...$fields, 'owner_id' => $user->id]);

                foreach (TaskStatus::whereNull('task_board_id')->orderBy('sort_order')->get() as $status) {
                    $board->statuses()->create($status->only(['name', 'color', 'sort_order', 'is_done', 'is_default']));
                }
            } else {
                $board->update($fields);
            }

            if (array_key_exists('members', $data)) {
                $board->members()->get()->each->delete();

                foreach (collect($data['members'] ?? [])->unique('user_id')->where('user_id', '!=', $board->owner_id) as $member) {
                    $board->members()->create(['user_id' => $member['user_id'], 'role' => $member['role'] ?? 'editor']);
                }
            }

            return $board->load(['statuses', 'members.user:id,name']);
        });
    }

    public function deleteBoard(TaskBoard $board): void
    {
        if ($board->tasks()->whereNull('completed_at')->exists()) {
            throw ValidationException::withMessages(['board' => ['This board still has open tasks; finish or move them, or archive the board.']]);
        }

        DB::transaction(function () use ($board): void {
            $board->members()->get()->each->delete();
            $board->statuses()->get()->each->delete();
            $board->delete();
        });
    }

    /**
     * Kanban data: the board's columns with their tasks in order.
     *
     * @return Collection<int, TaskStatus>
     */
    public function kanban(TaskBoard $board): Collection
    {
        $tasks = $this->withCard(Task::where('task_board_id', $board->id)->whereNull('parent_task_id'))
            ->orderBy('sort_order')->orderBy('id')->get()->groupBy('task_status_id');

        return $board->statuses()->get()->each(fn (TaskStatus $status) => $status->setRelation('tasks', $tasks->get($status->id, new Collection)));
    }

    /**
     * Columns a task can use: its board's, or the default set for personal tasks.
     *
     * @return Collection<int, TaskStatus>
     */
    public function statusesFor(?int $boardId): Collection
    {
        return TaskStatus::where('task_board_id', $boardId)->orderBy('sort_order')->get();
    }

    /* ---------- Tasks ---------- */

    /**
     * "My Tasks" and the task grid: assigned to me or created by me, with filters.
     *
     * @param  array{scope?: string|null, board_id?: int|null, state?: string|null}  $filters
     */
    public function tasksFor(User $user, array $filters = []): Builder
    {
        return $this->withCard(Task::query())
            ->when(($filters['scope'] ?? 'mine') === 'mine', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->whereHas('assignees', fn (Builder $assignee) => $assignee->where('user_id', $user->id))
                ->orWhere('created_by', $user->id)))
            ->when(($filters['scope'] ?? null) === 'assigned_by_me', fn (Builder $query) => $query->where('created_by', $user->id)->whereHas('assignees', fn (Builder $assignee) => $assignee->where('user_id', '!=', $user->id)))
            ->when($filters['board_id'] ?? null, fn (Builder $query, int $boardId) => $query->where('task_board_id', $boardId))
            ->when(($filters['state'] ?? null) === 'open', fn (Builder $query) => $query->whereNull('completed_at'))
            ->when(($filters['state'] ?? null) === 'overdue', fn (Builder $query) => $query->whereNull('completed_at')->where('due_at', '<', now()))
            ->when(($filters['state'] ?? null) === 'done', fn (Builder $query) => $query->whereNotNull('completed_at'));
    }

    /**
     * @param  array{scope?: string|null, board_id?: int|null, state?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function datatable(User $user, array $filters): array
    {
        return DataTables::eloquent($this->tasksFor($user, $filters))
            ->addColumn('status_name', fn (Task $task): ?string => $task->status?->name)
            ->addColumn('status_color', fn (Task $task): ?string => $task->status?->color)
            ->addColumn('priority_name', fn (Task $task): ?string => $task->priority?->name)
            ->addColumn('board_name', fn (Task $task): ?string => $task->board?->name)
            ->addColumn('assignee_names', fn (Task $task): string => $task->assignees->pluck('user.name')->filter()->implode(', '))
            ->addColumn('is_overdue', fn (Task $task): bool => $task->completed_at === null && $task->due_at?->isPast() === true)
            ->editColumn('due_at', fn (Task $task): ?string => $task->due_at?->toIso8601String())
            ->removeColumn('status', 'priority', 'board', 'assignees', 'checklist')
            ->toArray();
    }

    /**
     * A task the user may open: assigned, created, watching, or on a board they can see.
     */
    public function find(User $user, int $id): Task
    {
        $task = Task::with([
            'status:id,name,color,is_done', 'priority:id,name', 'category:id,name', 'board:id,name,owner_id,visibility,department_id', 'creator:id,name',
            'assignees.user:id,name', 'watchers.user:id,name', 'checklist', 'comments' => fn ($query) => $query->with('user:id,name')->oldest('id'),
            'subtasks:id,parent_task_id,task_no,title,completed_at',
        ])->findOrFail($id);

        $involved = $task->created_by === $user->id || $task->assignees->contains('user_id', $user->id) || $task->watchers->contains('user_id', $user->id);

        if (! $involved && ! $user->hasRole('admin') && ($task->board === null || ! $this->canView($user, $task->board))) {
            abort(404);
        }

        return $task;
    }

    /**
     * @param  array<string, mixed>  $data  task fields + assignee_ids[], watcher_ids[], checklist[{title, is_done}]
     */
    public function save(User $user, ?Task $task, array $data): Task
    {
        $boardId = array_key_exists('task_board_id', $data) ? $data['task_board_id'] : $task?->task_board_id;

        if ($boardId !== null && ! $this->canEdit($user, TaskBoard::findOrFail($boardId))) {
            throw ValidationException::withMessages(['task_board_id' => ['You can only view this board.']]);
        }

        $statusId = $data['task_status_id'] ?? $task?->task_status_id ?? $this->statusesFor($boardId)->first()?->id;

        if (! $this->statusesFor($boardId)->contains('id', $statusId)) {
            throw ValidationException::withMessages(['task_status_id' => ['Choose a column of this board.']]);
        }

        $saved = DB::transaction(function () use ($user, $task, $data, $statusId): Task {
            $fields = [...collect($data)->except(['assignee_ids', 'watcher_ids', 'checklist'])->all(), 'task_status_id' => $statusId];
            $isNew = $task === null;
            $oldDue = $task?->due_at?->toDateTimeString();

            $task = $isNew
                ? Task::create([...$fields, 'task_no' => $this->numberSequenceService->next('task_no'), 'recurrence' => $fields['recurrence'] ?? 'none'])
                : tap($task)->update($fields);

            $this->applyStatus($task->refresh(), $user);

            if (array_key_exists('assignee_ids', $data)) {
                $this->syncAssignees($task, $data['assignee_ids'] ?? [], $user);
            }

            if (array_key_exists('watcher_ids', $data)) {
                $task->watchers()->whereNotIn('user_id', $data['watcher_ids'] ?? [])->get()->each->delete();

                foreach (array_diff($data['watcher_ids'] ?? [], $task->watchers()->pluck('user_id')->all()) as $userId) {
                    $task->watchers()->create(['user_id' => $userId]);
                }
            }

            if (array_key_exists('checklist', $data)) {
                $task->checklist()->get()->each->delete();

                foreach (array_values($data['checklist'] ?? []) as $position => $item) {
                    $done = (bool) ($item['is_done'] ?? false);
                    $task->checklist()->create(['title' => $item['title'], 'is_done' => $done, 'done_by' => $done ? $user->id : null, 'done_at' => $done ? now() : null, 'sort_order' => $position]);
                }
            }

            if ($isNew || $oldDue !== $task->due_at?->toDateTimeString() || array_key_exists('assignee_ids', $data)) {
                $this->scheduleReminders($task);
            }

            return $task;
        });

        $this->broadcast($saved, $task === null ? 'created' : 'updated');

        return $this->find($user, $saved->id);
    }

    /**
     * Kanban drag: new column and position. Moving into a "done" column completes the task.
     */
    public function move(User $user, Task $task, int $statusId, ?int $sortOrder): Task
    {
        if ($task->board !== null && ! $this->canEdit($user, $task->board)) {
            throw ValidationException::withMessages(['task' => ['You can only view this board.']]);
        }

        if (! $this->statusesFor($task->task_board_id)->contains('id', $statusId)) {
            throw ValidationException::withMessages(['task_status_id' => ['Choose a column of this board.']]);
        }

        DB::transaction(function () use ($user, $task, $statusId, $sortOrder): void {
            $from = $task->status?->name;
            $task->update(['task_status_id' => $statusId, 'sort_order' => $sortOrder ?? $task->sort_order]);
            $this->applyStatus($task->refresh(), $user);

            if ($from !== $task->status->name) {
                $this->activityLogger->record('tasks', 'moved', "{$user->name} moved {$task->task_no} from {$from} to {$task->status->name}", $task);
            }
        });

        $this->broadcast($task, 'moved');

        return $task;
    }

    public function toggleChecklistItem(User $user, TaskChecklistItem $item): TaskChecklistItem
    {
        $item->update(['is_done' => ! $item->is_done, 'done_by' => $item->is_done ? null : $user->id, 'done_at' => $item->is_done ? null : now()]);
        $this->broadcast(Task::findOrFail($item->task_id), 'checklist');

        return $item;
    }

    /**
     * @param  array<int, int>  $mentionIds
     */
    public function comment(User $user, Task $task, string $body, array $mentionIds): TaskComment
    {
        $comment = DB::transaction(function () use ($user, $task, $body, $mentionIds): TaskComment {
            $comment = $task->comments()->create(['user_id' => $user->id, 'body' => $body]);

            foreach (array_unique($mentionIds) as $userId) {
                TaskCommentMention::create(['task_comment_id' => $comment->id, 'user_id' => $userId]);
            }

            $people = $task->assignees()->pluck('user_id')->merge($task->watchers()->pluck('user_id'))->push($task->created_by)->merge($mentionIds)
                ->unique()->reject(fn ($id): bool => (int) $id === $user->id)->values()->all();
            $this->notificationService->notify($people, 'task_comment', "{$user->name} commented on {$task->task_no}", $body, route('tasks.index', ['task' => $task->id]), $task);

            return $comment;
        });

        $this->broadcast($task, 'commented');

        return $comment->load('user:id,name');
    }

    public function delete(Task $task): void
    {
        DB::transaction(function () use ($task): void {
            foreach (['assignees', 'watchers', 'checklist', 'comments', 'subtasks'] as $relation) {
                $task->{$relation}()->get()->each->delete();
            }

            TaskReminder::where('task_id', $task->id)->get()->each->delete();
            $task->delete();
        });

        $this->broadcast($task, 'deleted');
    }

    /**
     * Scheduler: bell reminders for open tasks that are due soon. Returns how many were sent.
     */
    public function remindDue(): int
    {
        $due = TaskReminder::whereNull('sent_at')->where('remind_at', '<=', now())->get();
        $tasks = Task::whereIn('id', $due->pluck('task_id'))->get()->keyBy('id');

        foreach ($due as $reminder) {
            $task = $tasks->get($reminder->task_id);

            if ($task !== null && $task->completed_at === null) {
                $this->notificationService->notify([$reminder->user_id], 'task_due', "Due {$task->due_at?->diffForHumans()}: {$task->title}", $task->task_no, route('tasks.index', ['task' => $task->id]), $task, false, ['in_app', 'push']);
            }

            $reminder->update(['sent_at' => now()]);
        }

        return $due->count();
    }

    /**
     * Eager loads for a task card (grid row / Kanban card).
     */
    private function withCard(Builder $query): Builder
    {
        return $query->with(['status:id,name,color,is_done', 'priority:id,name', 'board:id,name', 'assignees.user:id,name'])
            ->withCount(['checklist', 'checklist as checklist_done_count' => fn (Builder $inner) => $inner->where('is_done', true), 'comments']);
    }

    /**
     * Completing (a "done" column) stamps completed_at and schedules the next copy of a recurring task.
     */
    private function applyStatus(Task $task, User $user): void
    {
        $isDone = (bool) $task->status?->is_done;

        if ($isDone && $task->completed_at === null) {
            $task->update(['completed_at' => now(), 'completed_by' => $user->id]);
            $this->activityLogger->record('tasks', 'completed', "{$user->name} completed {$task->task_no} {$task->title}", $task);
            $this->repeat($task);
        } elseif (! $isDone && $task->completed_at !== null) {
            $task->update(['completed_at' => null, 'completed_by' => null]);
        }
    }

    private function repeat(Task $task): void
    {
        if ($task->recurrence === 'none' || $task->due_at === null) {
            return;
        }

        $nextDue = match ($task->recurrence) {
            'daily' => $task->due_at->copy()->addDay(),
            'weekly' => $task->due_at->copy()->addWeek(),
            default => $task->due_at->copy()->addMonth(),
        };

        if ($task->recurrence_until !== null && $nextDue->toDateString() > $task->recurrence_until->toDateString()) {
            return;
        }

        $next = Task::create([
            ...$task->only(['task_board_id', 'title', 'description', 'priority_id', 'category_id', 'estimated_minutes', 'recurrence', 'recurrence_until']),
            'task_no' => $this->numberSequenceService->next('task_no'),
            'task_status_id' => $this->statusesFor($task->task_board_id)->first()->id,
            'due_at' => $nextDue,
        ]);

        foreach ($task->assignees()->get() as $assignee) {
            $next->assignees()->create(['user_id' => $assignee->user_id, 'assigned_by' => $assignee->assigned_by, 'assigned_at' => now()]);
        }

        $this->scheduleReminders($next);
    }

    /**
     * @param  array<int, int>  $userIds
     */
    private function syncAssignees(Task $task, array $userIds, User $user): void
    {
        $current = $task->assignees()->pluck('user_id')->all();
        $task->assignees()->whereNotIn('user_id', $userIds)->get()->each->delete();
        $added = array_values(array_diff($userIds, $current));

        foreach ($added as $userId) {
            $task->assignees()->create(['user_id' => $userId, 'assigned_by' => $user->id, 'assigned_at' => now()]);
        }

        if ($added !== []) {
            $names = User::whereIn('id', $added)->pluck('name')->implode(', ');
            $this->activityLogger->record('tasks', 'assigned', "{$user->name} assigned {$task->task_no} to {$names}", $task);
            $this->notificationService->notify(array_diff($added, [$user->id]), 'task_assigned', "{$user->name} assigned you {$task->task_no}", $task->title, route('tasks.index', ['task' => $task->id]), $task, false, ['in_app', 'push']);
        }
    }

    /**
     * One reminder per assignee: a day before the due time, or an hour before when that is sooner.
     */
    private function scheduleReminders(Task $task): void
    {
        TaskReminder::where('task_id', $task->id)->whereNull('sent_at')->get()->each->delete();

        if ($task->due_at === null || $task->completed_at !== null) {
            return;
        }

        $remindAt = $task->due_at->copy()->subDay()->isFuture() ? $task->due_at->copy()->subDay() : $task->due_at->copy()->subHour();

        foreach ($task->assignees()->pluck('user_id') as $userId) {
            TaskReminder::create(['task_id' => $task->id, 'user_id' => $userId, 'remind_at' => $remindAt]);
        }
    }

    private function broadcast(Task $task, string $action): void
    {
        $userIds = $task->assignees()->withTrashed()->pluck('user_id')->push($task->created_by)->filter()->map(fn ($id): int => (int) $id)->all();

        TaskChanged::dispatch($task->task_board_id, $userIds, $task->id, $action);
    }
}

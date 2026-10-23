<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Collaboration\SaveTaskBoardRequest;
use App\Http\Requests\Collaboration\SaveTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Employee;
use App\Models\Lookup;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaskChecklistItem;
use App\Models\TaskStatus;
use App\Models\User;
use App\Services\TaskService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Task boards, the Kanban view, My Tasks and task details (permission use-tasks). Web and mobile share it.
 */
class TaskController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private TaskService $taskService) {}

    /* ---------- Boards ---------- */

    public function boards(Request $request): JsonResponse
    {
        return $this->successResponse($this->taskService->boardsFor($request->user())->get(), 'Boards retrieved.');
    }

    public function showBoard(Request $request, TaskBoard $board): JsonResponse
    {
        abort_unless($this->taskService->canView($request->user(), $board), 404);

        return $this->successResponse([
            ...$board->load(['owner:id,name', 'department:id,name', 'members.user:id,name'])->toArray(),
            'can_edit' => $this->taskService->canEdit($request->user(), $board),
            'columns' => $this->taskService->kanban($board)->map(fn (TaskStatus $status): array => [
                ...$status->only(['id', 'name', 'color', 'is_done']),
                'tasks' => TaskResource::collection($status->tasks)->resolve(),
            ])->all(),
        ], 'Board retrieved.');
    }

    public function storeBoard(SaveTaskBoardRequest $request): JsonResponse
    {
        return $this->successResponse($this->taskService->saveBoard($request->user(), null, $request->validated()), 'Board created.', 201);
    }

    public function updateBoard(SaveTaskBoardRequest $request, TaskBoard $board): JsonResponse
    {
        return $this->successResponse($this->taskService->saveBoard($request->user(), $board, $request->validated()), 'Board updated.');
    }

    public function destroyBoard(Request $request, TaskBoard $board): JsonResponse
    {
        abort_unless($this->taskService->canEdit($request->user(), $board), 403, 'Only the owner or an editor can delete this board.');

        return $this->deleteWithApproval($board, 'Board deleted.');
    }

    /**
     * People who can be assigned (staff with a login, and administrators) plus the option lists of the task form.
     */
    public function options(Request $request): JsonResponse
    {
        $staffIds = Employee::whereIn('status', Employee::CURRENT_STATUSES)->whereNotNull('user_id')->pluck('user_id');

        return $this->successResponse([
            'people' => User::where('is_active', true)
                ->where(fn ($query) => $query->whereIn('id', $staffIds)->orWhereHas('roles', fn ($role) => $role->where('slug', 'admin')))
                ->orderBy('name')->get(['id', 'name']),
            'priorities' => Lookup::query()->ofType('task_priority')->active()->get(['id', 'name']),
            'categories' => Lookup::query()->ofType('task_category')->active()->get(['id', 'name']),
            'statuses' => $this->taskService->statusesFor($request->integer('board_id') ?: null)->map->only(['id', 'name', 'color', 'is_done'])->values(),
        ], 'Task options retrieved.');
    }

    /* ---------- Tasks ---------- */

    public function index(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        $tasks = $this->taskService->tasksFor($request->user(), $filters)->orderByRaw('completed_at is not null')->orderBy('due_at')->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return $this->paginatedResponse($tasks, TaskResource::class, 'Tasks retrieved.');
    }

    public function datatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->taskService->datatable($request->user(), $this->filters($request)), 'Tasks retrieved.');
    }

    public function show(Request $request, int $task): JsonResponse
    {
        return $this->successResponse(new TaskResource($this->taskService->find($request->user(), $task)), 'Task retrieved.');
    }

    public function store(SaveTaskRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['assignee_ids'] ??= [$request->user()->id];

        return $this->successResponse(new TaskResource($this->taskService->save($request->user(), null, $data)), 'Task added.', 201);
    }

    public function update(SaveTaskRequest $request, int $task): JsonResponse
    {
        $record = $this->taskService->find($request->user(), $task);

        return $this->successResponse(new TaskResource($this->taskService->save($request->user(), $record, $request->validated())), 'Task saved.');
    }

    public function move(Request $request, int $task): JsonResponse
    {
        $validated = $request->validate(['task_status_id' => ['required', 'integer'], 'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000']]);
        $record = $this->taskService->move($request->user(), $this->taskService->find($request->user(), $task), $validated['task_status_id'], $validated['sort_order'] ?? null);

        return $this->successResponse(new TaskResource($record->load('status')), $record->completed_at ? 'Done! 🎉' : 'Moved to '.$record->status->name.'.');
    }

    public function toggleChecklist(Request $request, int $task, int $item): JsonResponse
    {
        $record = $this->taskService->find($request->user(), $task);
        $toggled = $this->taskService->toggleChecklistItem($request->user(), TaskChecklistItem::where('task_id', $record->id)->findOrFail($item));

        return $this->successResponse($toggled->only(['id', 'title', 'is_done']), 'Checklist updated.');
    }

    public function comment(Request $request, int $task): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'mention_ids' => ['sometimes', 'array', 'max:20'],
            'mention_ids.*' => ['integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
        ]);
        $comment = $this->taskService->comment($request->user(), $this->taskService->find($request->user(), $task), $validated['body'], $validated['mention_ids'] ?? []);

        return $this->successResponse(['id' => $comment->id, 'user_id' => $comment->user_id, 'user_name' => $comment->user->name, 'body' => $comment->body, 'created_at' => $comment->created_at->toIso8601String()], 'Comment added.', 201);
    }

    public function destroy(Request $request, int $task): JsonResponse
    {
        return $this->deleteWithApproval($this->taskService->find($request->user(), $task), 'Task deleted.');
    }

    /**
     * @return array{scope: string|null, board_id: int|null, state: string|null}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'scope' => ['nullable', Rule::in(['mine', 'assigned_by_me', 'all'])],
            'board_id' => ['nullable', 'integer'],
            'state' => ['nullable', Rule::in(['open', 'overdue', 'done'])],
        ]);

        // Everyone except administrators only sees their own tasks in lists; boards show the rest.
        $scope = $validated['scope'] ?? 'mine';

        if ($scope === 'all' && ! $request->user()->hasRole('admin') && empty($validated['board_id'])) {
            $scope = 'mine';
        }

        if (! empty($validated['board_id']) && ! $this->taskService->canView($request->user(), TaskBoard::findOrFail($validated['board_id']))) {
            abort(404);
        }

        return ['scope' => $scope, 'board_id' => $validated['board_id'] ?? null, 'state' => $validated['state'] ?? null];
    }
}

<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A task was created, moved, edited, commented or deleted. Open Kanban boards and "My Tasks" screens reload.
 * Queued (the request never waits) and rescued (a stopped WebSocket server is not an error).
 */
class TaskChanged implements ShouldBroadcast, ShouldRescue
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  array<int, int>  $userIds  assignees and creator (their "My Tasks" lists)
     */
    public function __construct(public ?int $boardId, public array $userIds, public int $taskId, public string $action) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            ...($this->boardId ? [new PrivateChannel('tasks.board.'.$this->boardId)] : []),
            ...array_map(fn (int $userId): PrivateChannel => new PrivateChannel('App.Models.User.'.$userId), array_values(array_unique($this->userIds))),
        ];
    }

    public function broadcastAs(): string
    {
        return 'task.changed';
    }

    /**
     * @return array{task_id: int, board_id: int|null, action: string}
     */
    public function broadcastWith(): array
    {
        return ['task_id' => $this->taskId, 'board_id' => $this->boardId, 'action' => $this->action];
    }
}

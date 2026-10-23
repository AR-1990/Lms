<?php

use App\Models\TaskBoard;
use App\Models\User;
use App\Services\ChatService;
use App\Services\TaskService;
use Illuminate\Support\Facades\Broadcast;

/*
| Private channels: a user's own channel (bell, My Tasks, chat list) and task boards they can see.
| Chat: one private channel per conversation (participants only) and a presence channel for online status.
*/

Broadcast::channel('App.Models.User.{id}', fn (User $user, int $id): bool => $user->id === $id);

Broadcast::channel('tasks.board.{boardId}', function (User $user, int $boardId): bool {
    $board = TaskBoard::find($boardId);

    return $board !== null && $user->hasPermission('use-tasks') && app(TaskService::class)->canView($user, $board);
});

Broadcast::channel('chat.{conversationId}', fn (User $user, int $conversationId): bool => app(ChatService::class)->canJoin($user, $conversationId));

Broadcast::channel('chat.online', fn (User $user): ?array => $user->hasPermission('use-chat') ? ['id' => $user->id, 'name' => $user->name] : null);

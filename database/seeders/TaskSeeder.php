<?php

namespace Database\Seeders;

use App\Models\Lookup;
use App\Models\TaskStatus;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\TaskService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * The default Kanban columns (master data, used by personal tasks and copied to every new board) and a
 * demo "Annual Sports Day" board with a few assigned tasks.
 */
class TaskSeeder extends Seeder
{
    public function run(TaskService $taskService): void
    {
        foreach ([['To Do', '#64748b', false, true], ['In Progress', '#2563eb', false, false], ['Review', '#d97706', false, false], ['Done', '#16a34a', true, false]] as $position => [$name, $color, $isDone, $isDefault]) {
            TaskStatus::updateOrCreate(['task_board_id' => null, 'name' => $name], ['color' => $color, 'sort_order' => $position, 'is_done' => $isDone, 'is_default' => $isDefault]);
        }

        $admin = User::where('email', 'admin@lms.test')->first();
        $teacher = User::where('email', 'teacher@lms.test')->first();

        if ($admin === null || $teacher === null || $admin->ownedTaskBoards()->exists()) {
            return;
        }

        Auth::setUser($admin);
        ActivityLogger::quietly(fn () => $this->demoBoard($taskService, $admin, $teacher));
        Auth::forgetUser();
    }

    private function demoBoard(TaskService $taskService, User $admin, User $teacher): void
    {
        $priority = fn (string $name): ?int => Lookup::where('type', 'task_priority')->where('name', $name)->value('id');
        $board = $taskService->saveBoard($admin, null, [
            'name' => 'Annual Sports Day 2026', 'description' => 'Everything for the sports day on 20 December.', 'visibility' => 'everyone', 'color' => '#7c3aed',
            'members' => [['user_id' => $teacher->id, 'role' => 'editor']],
        ]);
        $columns = $board->statuses->pluck('id', 'name');

        foreach ([
            ['Book the sports ground', 'To Do', 'High', [$admin->id], 7, [['title' => 'Call the club'], ['title' => 'Pay the advance']]],
            ['House team lists for Grade 5', 'In Progress', 'Normal', [$teacher->id], 3, []],
            ['Order medals and trophies', 'To Do', 'Urgent', [$admin->id, $teacher->id], 1, [['title' => 'Get three quotes']]],
            ['Send invitation to parents', 'Done', 'Normal', [$admin->id], -2, []],
        ] as [$title, $column, $priorityName, $assignees, $dueInDays, $checklist]) {
            $taskService->save($admin, null, [
                'title' => $title, 'task_board_id' => $board->id, 'task_status_id' => $columns[$column], 'priority_id' => $priority($priorityName),
                'due_at' => now()->addDays($dueInDays)->setTime(15, 0), 'assignee_ids' => $assignees, 'checklist' => $checklist,
            ]);
        }
    }
}

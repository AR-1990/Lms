<?php

namespace App\Http\Controllers\Portal;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Lookup;
use App\Models\TaskBoard;
use App\Services\LeaveService;
use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tasks and staff chat screens. Their data comes from /api/erp/tasks, /api/erp/task-boards and /api/erp/chat.
 */
class CollaborationPortalController extends PortalController
{
    public function myTasks(Request $request): View
    {
        return $this->page('dashboards.tasks.my-tasks', $this->portalSlug(), 'tasks', ['open_id' => $request->integer('task') ?: null]);
    }

    public function boards(): View
    {
        return $this->page('dashboards.tasks.boards', $this->portalSlug(), 'tasks', [
            'departments' => Department::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function board(Request $request, TaskBoard $board, TaskService $taskService): View
    {
        abort_unless($taskService->canView($request->user(), $board), 404);

        return $this->page('dashboards.tasks.board', $this->portalSlug(), 'tasks', ['board' => $board]);
    }

    public function chat(Request $request): View
    {
        return $this->page('dashboards.chat', $this->portalSlug(), 'chat', ['open_id' => $request->integer('conversation') ?: null]);
    }

    /**
     * Chat oversight (data: /api/erp/chat-monitor). The admin reads chats without joining them.
     */
    public function chatMonitor(Request $request): View
    {
        return $this->page('dashboards.chat-monitor', $this->portalSlug(), 'chat_monitor', ['open_id' => $request->integer('conversation') ?: null]);
    }

    /**
     * Employee self-service: own leave, payslips and loans, plus the team's leave for managers.
     */
    public function myHr(): View
    {
        $user = $this->portalUser();
        $employee = Employee::where('user_id', $user->id)->first();

        return $this->page('dashboards.my-hr', $this->portalSlug(), 'my_hr', [
            'employee' => $employee,
            'leave_types' => $employee ? app(LeaveService::class)->typesFor($employee) : collect(),
            'loan_types' => Lookup::query()->ofType('loan_type')->active()->get(['id', 'name']),
            'is_manager' => $employee !== null && Employee::where('reports_to_id', $employee->id)->exists(),
        ]);
    }

    private function portalSlug(): string
    {
        return $this->portalUser()->roles->first()->slug ?? 'user';
    }
}

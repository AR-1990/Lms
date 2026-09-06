<?php

namespace App\Http\Controllers;

use App\Services\AccountsService;
use App\Services\AdminService;
use App\Services\ParentService;
use App\Services\StudentService;
use App\Services\TeacherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private AdminService $adminService,
        private TeacherService $teacherService,
        private StudentService $studentService,
        private ParentService $parentService,
        private AccountsService $accountsService,
    ) {}

    /**
     * Route the signed-in user to the dashboard that matches their role.
     */
    public function index(): View|RedirectResponse
    {
        $user = auth()->user()->load('roles');

        return match (true) {
            $user->hasRole('admin') => view('dashboards.admin', [
                'user' => $user,
                'data' => $this->adminService->getDashboardSummary(),
                'active' => 'dashboard',
            ]),
            $user->hasRole('teacher') => view('dashboards.teacher', [
                'user' => $user,
                'data' => $this->teacherService->getTeacherDashboard($user),
                'active' => 'dashboard',
            ]),
            $user->hasRole('student') => view('dashboards.student', [
                'user' => $user,
                'data' => $this->studentService->getStudentDashboard($user),
                'active' => 'dashboard',
            ]),
            $user->hasRole('parent') => view('dashboards.parent', [
                'user' => $user,
                'data' => $this->parentService->getParentDashboard($user),
                'active' => 'dashboard',
            ]),
            $user->hasRole('accounts') => view('dashboards.accounts', [
                'user' => $user,
                'data' => $this->accountsService->getAccountsDashboard($user),
                'active' => 'dashboard',
            ]),
            default => redirect()->route('home')->with('error', 'No portal access assigned to this account.'),
        };
    }
}

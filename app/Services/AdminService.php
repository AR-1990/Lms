<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

class AdminService
{
    public function __construct(private SystemSettingsService $systemSettingsService) {}

    /**
     * Get system-wide dashboard overview and statistics directly via Eloquent SQL.
     */
    public function getDashboardSummary(): array
    {
        $totalUsers = User::count();
        $totalAdmins = User::whereHas('roles', fn ($q) => $q->where('slug', 'admin'))->count();
        $totalTeachers = User::whereHas('roles', fn ($q) => $q->where('slug', 'teacher'))->count();
        $totalStudents = User::whereHas('roles', fn ($q) => $q->where('slug', 'student'))->count();
        $totalRoles = Role::count();

        $recentUsers = User::with('roles')->latest()->take(5)->get();
        $rolesBreakdown = Role::withCount('users')->orderBy('name')->get();
        $settings = $this->systemSettingsService->getSettingsPayload();

        return [
            'overview' => [
                'total_users' => $totalUsers,
                'total_admins' => $totalAdmins,
                'total_teachers' => $totalTeachers,
                'total_students' => $totalStudents,
                'total_roles' => $totalRoles,
            ],
            'recent_users' => $recentUsers,
            'roles_breakdown' => $rolesBreakdown,
            'system_status' => [
                'api_version' => '1.0.0',
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'status' => 'operational',
                'timezone' => $settings['timezone'],
                'school_name' => $settings['school_name'] ?? config('app.name'),
                'currency' => $settings['currency']['code'] ?? null,
            ],
        ];
    }

    /**
     * Full user directory for the admin portal.
     */
    public function getUsersDirectory(): array
    {
        $users = User::with('roles')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return [
            'users' => $users,
            'counts' => [
                'total' => $users->count(),
                'active_roles' => Role::count(),
            ],
        ];
    }

    /**
     * Roles and permission matrix for the admin portal.
     */
    public function getRolesDirectory(): array
    {
        $roles = Role::with(['permissions' => fn ($q) => $q->orderBy('group')->orderBy('name')])
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return [
            'roles' => $roles,
            'permission_groups' => Permission::query()
                ->orderBy('group')
                ->orderBy('name')
                ->get()
                ->groupBy('group'),
            'counts' => [
                'roles' => $roles->count(),
                'permissions' => Permission::count(),
            ],
        ];
    }

    /**
     * Operational reports snapshot for administrators.
     */
    public function getReportsSnapshot(): array
    {
        $summary = $this->getDashboardSummary();

        return [
            'overview' => $summary['overview'],
            'roles_breakdown' => $summary['roles_breakdown'],
            'reports' => [],
            'kpis' => [],
        ];
    }
}

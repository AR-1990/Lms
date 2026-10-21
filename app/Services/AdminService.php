<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;

class AdminService
{
    public function __construct(
        private SystemSettingsService $systemSettingsService,
        private LookupService $lookupService,
    ) {}

    /**
     * Get system-wide dashboard overview and statistics directly via Eloquent SQL.
     */
    public function getDashboardSummary(): array
    {
        $rolesBreakdown = Role::withCount('users')->orderBy('name')->get();
        $roleCounts = $rolesBreakdown->pluck('users_count', 'slug');
        $recentUsers = User::with('roles:id,name,slug')->latest()->latest('id')->take(5)->get();
        $settings = $this->systemSettingsService->getSettingsPayload();

        return [
            'overview' => [
                'total_users' => User::count(),
                'total_admins' => $roleCounts->get('admin', 0),
                'total_teachers' => $roleCounts->get('teacher', 0),
                'total_students' => $roleCounts->get('student', 0),
                'total_roles' => $rolesBreakdown->count(),
                'new_this_month' => User::where('created_at', '>=', now()->startOfMonth())->count(),
            ],
            'daily_quote' => $this->lookupService->dailyQuote(),
            'monthly_signups' => $this->getMonthlySignups(6),
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
     * New accounts per month for the last N months, oldest first (months with none included).
     *
     * @return array<int, array{label: string, count: int}>
     */
    private function getMonthlySignups(int $months): array
    {
        $firstMonth = now()->startOfMonth()->subMonths($months - 1);

        $countsByMonth = User::where('created_at', '>=', $firstMonth)
            ->pluck('created_at')
            ->countBy(fn ($createdAt): string => $createdAt->format('Y-m'));

        return collect(range(0, $months - 1))
            ->map(function (int $offset) use ($firstMonth, $countsByMonth): array {
                $month = $firstMonth->copy()->addMonths($offset);

                return [
                    'label' => $month->format('M'),
                    'count' => $countsByMonth->get($month->format('Y-m'), 0),
                ];
            })
            ->all();
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

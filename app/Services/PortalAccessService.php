<?php

namespace App\Services;

use App\Models\User;

class PortalAccessService
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private AdminService $adminService,
        private TeacherService $teacherService,
        private StudentService $studentService,
        private ParentService $parentService,
        private AccountsService $accountsService,
    ) {}

    /**
     * Get supported portal keys.
     *
     * @return array<int, string>
     */
    public function getPortalKeys(): array
    {
        return array_keys($this->getPortalDefinitions());
    }

    /**
     * Determine whether the user can access a portal through role or permission.
     */
    public function canAccessPortal(User $user, string $portal): bool
    {
        $definition = $this->getPortalDefinitions()[$portal] ?? null;

        if ($definition === null) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole($definition['roles'])) {
            return true;
        }

        return $user->hasPermission($definition['permissions']);
    }

    /**
     * Resolve the best dashboard for the signed-in user.
     *
     * @return array{portal: string, view: string, data: array<string, mixed>}|null
     */
    public function resolveDashboard(User $user, ?string $preferredPortal = null): ?array
    {
        $portalCandidates = $this->getPortalCandidates($user);

        if ($preferredPortal !== null && in_array($preferredPortal, $portalCandidates, true)) {
            $portalCandidates = [
                $preferredPortal,
                ...array_values(array_diff($portalCandidates, [$preferredPortal])),
            ];
        }

        foreach ($portalCandidates as $portal) {
            if (! $this->canAccessPortal($user, $portal)) {
                continue;
            }

            return [
                'portal' => $portal,
                'view' => $this->getPortalDefinitions()[$portal]['dashboard_view'],
                'data' => $this->getDashboardData($portal, $user),
            ];
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function getPortalCandidates(User $user): array
    {
        $candidates = [];

        if ($user->hasRole('admin')) {
            $candidates[] = 'admin';
        }

        foreach ($user->roles as $role) {
            if (array_key_exists($role->slug, $this->getPortalDefinitions())) {
                $candidates[] = $role->slug;
            }
        }

        return array_values(array_unique([
            ...$candidates,
            ...$this->getPortalKeys(),
        ]));
    }

    /**
     * @return array<string, array{roles: array<int, string>, permissions: array<int, string>, dashboard_view: string}>
     */
    private function getPortalDefinitions(): array
    {
        return [
            'admin' => [
                'roles' => ['admin'],
                'permissions' => ['manage-users', 'manage-roles', 'view-stats'],
                'dashboard_view' => 'dashboards.admin',
            ],
            'teacher' => [
                'roles' => ['teacher'],
                'permissions' => ['manage-classes', 'record-attendance', 'submit-grades'],
                'dashboard_view' => 'dashboards.teacher',
            ],
            'student' => [
                'roles' => ['student'],
                'permissions' => ['view-courses', 'view-attendance', 'view-grades'],
                'dashboard_view' => 'dashboards.student',
            ],
            'parent' => [
                'roles' => ['parent'],
                'permissions' => ['view-children', 'view-fees', 'view-attendance', 'view-grades', 'view-notices'],
                'dashboard_view' => 'dashboards.parent',
            ],
            'accounts' => [
                'roles' => ['accounts'],
                'permissions' => ['manage-fees', 'view-payroll', 'view-fees'],
                'dashboard_view' => 'dashboards.accounts',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getDashboardData(string $portal, User $user): array
    {
        return match ($portal) {
            'admin' => $this->adminService->getDashboardSummary(),
            'teacher' => $this->teacherService->getTeacherDashboard($user),
            'student' => $this->studentService->getStudentDashboard($user),
            'parent' => $this->parentService->getParentDashboard($user),
            'accounts' => $this->accountsService->getAccountsDashboard($user),
            default => [],
        };
    }
}

<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

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
        return collect($this->getPortalDefinitions())->keys()->values()->all();
    }

    /**
     * Get all portals the user can open.
     *
     * @return array<int, string>
     */
    public function getAccessiblePortals(User $user): array
    {
        return collect($this->getPortalKeys())
            ->filter(fn (string $portal): bool => $this->canAccessPortal($user, $portal))
            ->values()
            ->all();
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
        $portalCandidates = collect($this->getPortalCandidates($user));

        if ($preferredPortal !== null && $portalCandidates->contains($preferredPortal)) {
            $portalCandidates = $portalCandidates
                ->reject(fn (string $portal): bool => $portal === $preferredPortal)
                ->prepend($preferredPortal);
        }

        $selectedPortal = $portalCandidates
            ->first(fn (string $portal): bool => $this->canAccessPortal($user, $portal));

        if ($selectedPortal === null) {
            return null;
        }

        return [
            'portal' => $selectedPortal,
            'view' => $this->getPortalDefinitions()[$selectedPortal]['dashboard_view'],
            'data' => $this->getDashboardData($selectedPortal, $user),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function getPortalCandidates(User $user): array
    {
        $definitions = $this->getPortalDefinitions();

        return $user->roles
            ->pluck('slug')
            ->filter(fn (string $slug): bool => array_key_exists($slug, $definitions))
            ->when(
                $user->hasRole('admin'),
                fn (Collection $roles): Collection => $roles->prepend('admin'),
            )
            ->concat($this->getPortalKeys())
            ->unique()
            ->values()
            ->all();
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

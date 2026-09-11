<?php

namespace App\Services;

use App\Models\User;

class PortalAccessService
{
    private const PORTAL_DEFINITIONS = [
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

    private const ROLE_LABELS = [
        'admin' => 'Administration',
        'teacher' => 'Faculty',
        'student' => 'Student LMS',
        'parent' => 'Parent Portal',
        'accounts' => 'Accounts',
    ];

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
        return array_keys(self::PORTAL_DEFINITIONS);
    }

    /**
     * Get all portals the user can open.
     *
     * @return array<int, string>
     */
    public function getAccessiblePortals(User $user): array
    {
        return array_values(array_filter(
            $this->getPortalKeys(),
            fn (string $portal): bool => $this->canAccessPortal($user, $portal),
        ));
    }

    /**
     * Build sidebar configuration for the dashboard shell.
     *
     * @return array{role_slug: string, role_label: string, markup: string}
     */
    public function getSidebarConfig(User $user, ?string $portal = null, string $active = 'dashboard'): array
    {
        $roleSlug = $this->resolveSidebarRoleSlug($user, $portal);

        return [
            'role_slug' => $roleSlug,
            'role_label' => $this->getSidebarRoleLabel($roleSlug),
            'markup' => $this->getSidebarMarkup($user, $roleSlug, $active),
        ];
    }

    /**
     * Determine whether the user can access a portal through role or permission.
     */
    public function canAccessPortal(User $user, string $portal): bool
    {
        $definition = $this->getPortalDefinition($portal);

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
        $selectedPortal = $this->resolveSelectedPortal($user, $preferredPortal);

        if ($selectedPortal === null) {
            return null;
        }

        $definition = $this->getPortalDefinition($selectedPortal);

        return [
            'portal' => $selectedPortal,
            'view' => $definition['dashboard_view'],
            'data' => $this->getDashboardData($selectedPortal, $user),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function getPortalCandidates(User $user): array
    {
        if ($user->hasRole('admin')) {
            return array_values(array_unique(['admin', ...$this->getPortalKeys()]));
        }

        $roleSlugs = $user->roles->pluck('slug')->all();
        $portalRoles = array_values(array_filter(
            $roleSlugs,
            fn (string $slug): bool => isset(self::PORTAL_DEFINITIONS[$slug]),
        ));

        return array_values(array_unique([...$portalRoles, ...$this->getPortalKeys()]));
    }

    private function resolveSelectedPortal(User $user, ?string $preferredPortal = null): ?string
    {
        $resolvedPreferredPortal = $user->hasRole('admin') ? 'admin' : $preferredPortal;
        $portalCandidates = $this->getPortalCandidates($user);

        if ($resolvedPreferredPortal !== null && in_array($resolvedPreferredPortal, $portalCandidates, true)) {
            $portalCandidates = [
                $resolvedPreferredPortal,
                ...array_values(array_filter(
                    $portalCandidates,
                    fn (string $portal): bool => $portal !== $resolvedPreferredPortal,
                )),
            ];
        }

        return collect($portalCandidates)->first(
            fn (string $portal): bool => $this->canAccessPortal($user, $portal),
        );
    }

    /**
     * @return array{roles: array<int, string>, permissions: array<int, string>, dashboard_view: string}|null
     */
    private function getPortalDefinition(string $portal): ?array
    {
        return self::PORTAL_DEFINITIONS[$portal] ?? null;
    }

    private function resolveSidebarRoleSlug(User $user, ?string $portal = null): string
    {
        if ($user->hasRole('admin')) {
            return 'admin';
        }

        return $portal ?? $user->roles->first()->slug ?? 'user';
    }

    private function getSidebarRoleLabel(string $roleSlug): string
    {
        return self::ROLE_LABELS[$roleSlug] ?? 'Portal';
    }

    private function getSidebarMarkup(User $user, string $roleSlug, string $active): string
    {
        if ($user->hasRole('admin')) {
            return $this->getUniversalAdminSidebarMarkup($active);
        }

        return match ($roleSlug) {
            'admin' => $this->getAdminSidebarMarkup($active),
            'teacher' => $this->getTeacherSidebarMarkup($active),
            'student' => $this->getStudentSidebarMarkup($active),
            'parent' => $this->getParentSidebarMarkup($active),
            'accounts' => $this->getAccountsSidebarMarkup($active),
            default => $this->sidebarLink('dashboard', 'Overview', route('dashboard'), $active),
        };
    }

    private function getUniversalAdminSidebarMarkup(string $active): string
    {
        return $this->getAdminSidebarMarkup($active)
            .$this->sidebarLink('classes', 'Teacher Classes', route('teacher.classes'), $active)
            .$this->sidebarLink('attendance', 'Teacher Attendance', route('teacher.attendance'), $active)
            .$this->sidebarLink('grades', 'Teacher Grades', route('teacher.grades'), $active)
            .$this->sidebarLink('courses', 'Student Courses', route('student.courses'), $active)
            .$this->sidebarLink('student-attendance', 'Student Attendance', route('student.attendance'), $active)
            .$this->sidebarLink('student-grades', 'Student Grades', route('student.grades'), $active)
            .$this->sidebarLink('children', 'Parent Children', route('parent.children'), $active)
            .$this->sidebarLink('fees', 'Parent Fees', route('parent.fees'), $active)
            .$this->sidebarLink('notices', 'Parent Notices', route('parent.notices'), $active)
            .$this->sidebarLink('collections', 'Accounts Collections', route('accounts.collections'), $active)
            .$this->sidebarLink('challans', 'Accounts Challans', route('accounts.challans'), $active)
            .$this->sidebarLink('payroll', 'Accounts Payroll', route('accounts.payroll'), $active);
    }

    private function getAdminSidebarMarkup(string $active): string
    {
        return $this->sidebarLink('dashboard', 'Overview', route('dashboard'), $active)
            .$this->sidebarLink('users', 'Users', route('admin.users'), $active)
            .$this->sidebarLink('roles', 'Roles', route('admin.roles'), $active)
            .$this->sidebarLink('reports', 'Reports', route('admin.reports'), $active)
            .$this->sidebarLink('settings', 'Settings', route('admin.settings'), $active);
    }

    private function getTeacherSidebarMarkup(string $active): string
    {
        return $this->sidebarLink('dashboard', 'Overview', route('dashboard'), $active)
            .$this->sidebarLink('classes', 'My Classes', route('teacher.classes'), $active)
            .$this->sidebarLink('attendance', 'Attendance', route('teacher.attendance'), $active)
            .$this->sidebarLink('grades', 'Gradebook', route('teacher.grades'), $active);
    }

    private function getStudentSidebarMarkup(string $active): string
    {
        return $this->sidebarLink('dashboard', 'Overview', route('dashboard'), $active)
            .$this->sidebarLink('courses', 'Courses', route('student.courses'), $active)
            .$this->sidebarLink('attendance', 'Attendance', route('student.attendance'), $active)
            .$this->sidebarLink('grades', 'Grades', route('student.grades'), $active);
    }

    private function getParentSidebarMarkup(string $active): string
    {
        return $this->sidebarLink('dashboard', 'Overview', route('dashboard'), $active)
            .$this->sidebarLink('children', 'Children', route('parent.children'), $active)
            .$this->sidebarLink('fees', 'Fees', route('parent.fees'), $active)
            .$this->sidebarLink('notices', 'Notices', route('parent.notices'), $active);
    }

    private function getAccountsSidebarMarkup(string $active): string
    {
        return $this->sidebarLink('dashboard', 'Overview', route('dashboard'), $active)
            .$this->sidebarLink('collections', 'Collections', route('accounts.collections'), $active)
            .$this->sidebarLink('challans', 'Challans', route('accounts.challans'), $active)
            .$this->sidebarLink('payroll', 'Payroll', route('accounts.payroll'), $active);
    }

    private function sidebarLink(string $key, string $label, string $href, string $active): string
    {
        $activeClass = $active === $key ? ' is-active' : '';

        return '<a href="'.$href.'" class="dash-nav-link'.$activeClass.'">'.$label.'</a>';
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

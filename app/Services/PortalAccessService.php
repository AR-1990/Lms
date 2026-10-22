<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;

class PortalAccessService
{
    /**
     * @var array<string, array<int, array{key: string, label: string, route: string, icon: string, tone: string}>>
     */
    private const NAVIGATION_LINKS = [
        'admin' => [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'grid', 'tone' => 'orange'],
            ['key' => 'users', 'label' => 'Users', 'route' => 'admin.users', 'icon' => 'users', 'tone' => 'blue'],
            ['key' => 'roles', 'label' => 'Roles', 'route' => 'admin.roles', 'icon' => 'shield', 'tone' => 'violet'],
            ['key' => 'students', 'label' => 'Students', 'route' => 'admin.students', 'icon' => 'student', 'tone' => 'blue'],
            ['key' => 'admissions', 'label' => 'Admissions', 'route' => 'admin.admissions.applications', 'icon' => 'user-plus', 'tone' => 'violet'],
            ['key' => 'fees', 'label' => 'Fees', 'route' => 'accounts.fees.challans', 'icon' => 'hash', 'tone' => 'amber'],
            ['key' => 'hr', 'label' => 'HR', 'route' => 'admin.hr.employees', 'icon' => 'teacher', 'tone' => 'rose'],
            ['key' => 'attendance', 'label' => 'Attendance', 'route' => 'admin.attendance.register', 'icon' => 'check', 'tone' => 'emerald'],
            ['key' => 'diary', 'label' => 'Diary', 'route' => 'admin.diary', 'icon' => 'book', 'tone' => 'violet'],
            ['key' => 'homework', 'label' => 'Homework', 'route' => 'admin.homework', 'icon' => 'pen', 'tone' => 'amber'],
            ['key' => 'exams', 'label' => 'Exams', 'route' => 'admin.exams.index', 'icon' => 'sparkles', 'tone' => 'rose'],
            ['key' => 'performance', 'label' => 'Performance', 'route' => 'admin.performance.overview', 'icon' => 'trending-up', 'tone' => 'emerald'],
            ['key' => 'academics', 'label' => 'Academics', 'route' => 'admin.academics.classes', 'icon' => 'layers', 'tone' => 'sky'],
            ['key' => 'accounting', 'label' => 'Accounts', 'route' => 'accounts.chart', 'icon' => 'book', 'tone' => 'emerald'],
            ['key' => 'lookups', 'label' => 'Lookups', 'route' => 'admin.lookups', 'icon' => 'layers', 'tone' => 'amber'],
            ['key' => 'campuses', 'label' => 'Campuses', 'route' => 'admin.campuses', 'icon' => 'building', 'tone' => 'emerald'],
            ['key' => 'numbering', 'label' => 'Numbering', 'route' => 'admin.numbering', 'icon' => 'hash', 'tone' => 'blue'],
            ['key' => 'id_cards', 'label' => 'ID Cards', 'route' => 'admin.cards.index', 'icon' => 'key', 'tone' => 'amber'],
            ['key' => 'website', 'label' => 'Website', 'route' => 'admin.website.posts', 'icon' => 'globe', 'tone' => 'sky'],
            ['key' => 'approvals', 'label' => 'Approvals', 'route' => 'admin.approvals', 'icon' => 'shield', 'tone' => 'violet'],
            ['key' => 'activity_log', 'label' => 'Activity Log', 'route' => 'admin.activity-log', 'icon' => 'activity', 'tone' => 'sky'],
            ['key' => 'reports', 'label' => 'Reports', 'route' => 'admin.reports', 'icon' => 'chart', 'tone' => 'emerald'],
            ['key' => 'settings', 'label' => 'Settings', 'route' => 'admin.settings', 'icon' => 'settings', 'tone' => 'rose'],
        ],
        'teacher' => [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'grid', 'tone' => 'orange'],
            ['key' => 'attendance', 'label' => 'Attendance', 'route' => 'admin.attendance.register', 'icon' => 'check', 'tone' => 'emerald'],
            ['key' => 'diary', 'label' => 'Diary', 'route' => 'admin.diary', 'icon' => 'book', 'tone' => 'violet'],
            ['key' => 'homework', 'label' => 'Homework', 'route' => 'admin.homework', 'icon' => 'pen', 'tone' => 'amber'],
            ['key' => 'marks', 'label' => 'Marks Entry', 'route' => 'admin.exams.marks', 'icon' => 'chart', 'tone' => 'rose'],
            ['key' => 'performance', 'label' => 'Performance', 'route' => 'admin.performance.overview', 'icon' => 'trending-up', 'tone' => 'emerald'],
        ],
        'student' => [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'grid', 'tone' => 'orange'],
        ],
        'parent' => [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'grid', 'tone' => 'orange'],
        ],
        'accounts' => [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'grid', 'tone' => 'orange'],
            ['key' => 'fees', 'label' => 'Fees', 'route' => 'accounts.fees.challans', 'icon' => 'hash', 'tone' => 'amber'],
            ['key' => 'accounting', 'label' => 'Accounts', 'route' => 'accounts.chart', 'icon' => 'book', 'tone' => 'emerald'],
        ],
    ];

    public function __construct(private AdminService $adminService) {}

    /**
     * @var array<string, Role|null>
     */
    private array $roleCache = [];

    /**
     * @var array<int, string>|null
     */
    private ?array $portalKeysCache = null;

    /**
     * Get supported portal keys.
     *
     * @return array<int, string>
     */
    public function getPortalKeys(): array
    {
        if ($this->portalKeysCache !== null) {
            return $this->portalKeysCache;
        }

        $this->portalKeysCache = Role::query()
            ->whereNotNull('dashboard_view')
            ->pluck('slug')
            ->all();

        return $this->portalKeysCache;
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
            ->all();
    }

    /**
     * Build top navigation configuration for the dashboard shell.
     *
     * @return array{role_slug: string, role_label: string, links: array<int, array{key: string, label: string, url: string, icon: string, tone: string, active: bool}>}
     */
    public function getNavigationConfig(User $user, ?string $portal = null, string $active = 'dashboard'): array
    {
        $roleSlug = $this->resolveNavigationRoleSlug($user, $portal);

        return [
            'role_slug' => $roleSlug,
            'role_label' => $this->getNavigationRoleLabel($roleSlug),
            'links' => $this->getNavigationLinks($roleSlug, $active),
        ];
    }

    /**
     * Determine whether the user can access a portal through role or permission.
     */
    public function canAccessPortal(User $user, string $portal): bool
    {
        if (! filled($this->getPortalDashboardView($portal))) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole($portal)) {
            return true;
        }

        $permissionSlugs = $this->getPortalPermissionSlugs($portal);

        if ($permissionSlugs === []) {
            return false;
        }

        return $user->hasPermission($permissionSlugs);
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

        $dashboardView = $this->getPortalDashboardView($selectedPortal);

        if (! filled($dashboardView)) {
            return null;
        }

        return [
            'portal' => $selectedPortal,
            'view' => $dashboardView,
            'data' => $this->getDashboardData($selectedPortal, $user),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function getPortalCandidates(User $user): array
    {
        if ($user->hasRole('admin')) {
            return collect(['admin', ...$this->getPortalKeys()])
                ->unique()
                ->all();
        }

        $portalKeys = collect($this->getPortalKeys());
        $roleSlugs = $user->roles->pluck('slug')->all();
        $portalRoles = collect($roleSlugs)
            ->filter(fn (string $slug): bool => $portalKeys->contains($slug))
            ->all();

        return collect([...$portalRoles, ...$this->getPortalKeys()])
            ->unique()
            ->all();
    }

    private function resolveSelectedPortal(User $user, ?string $preferredPortal = null): ?string
    {
        $resolvedPreferredPortal = $user->hasRole('admin') ? 'admin' : $preferredPortal;
        $portalCandidates = $this->getPortalCandidates($user);

        if ($resolvedPreferredPortal !== null && in_array($resolvedPreferredPortal, $portalCandidates, true)) {
            $portalCandidates = [
                $resolvedPreferredPortal,
                ...collect($portalCandidates)
                    ->filter(fn (string $portal): bool => $portal !== $resolvedPreferredPortal)
                    ->all(),
            ];
        }

        return collect($portalCandidates)->first(
            fn (string $portal): bool => $this->canAccessPortal($user, $portal),
        );
    }

    private function getCachedRole(string $slug): ?Role
    {
        if (array_key_exists($slug, $this->roleCache)) {
            return $this->roleCache[$slug];
        }

        $this->roleCache[$slug] = Role::query()
            ->with('permissions:id,slug')
            ->where('slug', $slug)
            ->first();

        return $this->roleCache[$slug];
    }

    private function getPortalDashboardView(string $portal): ?string
    {
        $dashboardView = $this->getCachedRole($portal)?->dashboard_view;

        if (! filled($dashboardView)) {
            return null;
        }

        return $dashboardView;
    }

    /**
     * @return array<int, string>
     */
    private function getPortalPermissionSlugs(string $portal): array
    {
        $portalRole = $this->getCachedRole($portal);

        if ($portalRole === null) {
            return [];
        }

        return $portalRole->permissions->pluck('slug')->filter()->all();
    }

    private function resolveNavigationRoleSlug(User $user, ?string $portal = null): string
    {
        if ($user->hasRole('admin')) {
            return 'admin';
        }

        return $portal ?? $user->roles->first()->slug ?? 'user';
    }

    private function getNavigationRoleLabel(string $roleSlug): string
    {
        $roleName = $this->getCachedRole($roleSlug)?->name;

        if (filled($roleName)) {
            return $roleName;
        }

        return $roleSlug === 'user' ? 'Portal' : Str::headline($roleSlug);
    }

    /**
     * @return array<int, array{key: string, label: string, url: string, icon: string, tone: string, active: bool}>
     */
    private function getNavigationLinks(string $roleSlug, string $active): array
    {
        $navigationLinks = self::NAVIGATION_LINKS[$roleSlug] ?? [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'grid', 'tone' => 'orange'],
        ];

        return collect($navigationLinks)
            ->map(fn (array $link): array => [
                'key' => $link['key'],
                'label' => $link['label'],
                'url' => route($link['route']),
                'icon' => $link['icon'],
                'tone' => $link['tone'],
                'active' => $link['key'] === $active,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function getDashboardData(string $portal, User $user): array
    {
        $dashboardData = [
            'portal' => $portal,
            'message' => 'Welcome to LMS',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ];

        if ($portal === 'admin') {
            return [...$dashboardData, ...$this->adminService->getDashboardSummary()];
        }

        return $dashboardData;
    }
}

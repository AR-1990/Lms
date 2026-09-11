<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;

class PortalAccessService
{
    /**
     * @var array<string, array<int, array{key: string, label: string, route: string}>>
     */
    private const SIDEBAR_LINKS = [
        'admin' => [
            ['key' => 'dashboard', 'label' => 'Overview', 'route' => 'dashboard'],
            ['key' => 'settings', 'label' => 'Settings', 'route' => 'admin.settings'],
        ],
        'teacher' => [
            ['key' => 'dashboard', 'label' => 'Overview', 'route' => 'dashboard'],
        ],
        'student' => [
            ['key' => 'dashboard', 'label' => 'Overview', 'route' => 'dashboard'],
        ],
        'parent' => [
            ['key' => 'dashboard', 'label' => 'Overview', 'route' => 'dashboard'],
        ],
        'accounts' => [
            ['key' => 'dashboard', 'label' => 'Overview', 'route' => 'dashboard'],
        ],
    ];

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

    private function resolveSidebarRoleSlug(User $user, ?string $portal = null): string
    {
        if ($user->hasRole('admin')) {
            return 'admin';
        }

        return $portal ?? $user->roles->first()->slug ?? 'user';
    }

    private function getSidebarRoleLabel(string $roleSlug): string
    {
        $roleName = $this->getCachedRole($roleSlug)?->name;

        if (filled($roleName)) {
            return $roleName;
        }

        return $roleSlug === 'user' ? 'Portal' : Str::headline($roleSlug);
    }

    private function getSidebarMarkup(User $user, string $roleSlug, string $active): string
    {
        $sidebarLinks = self::SIDEBAR_LINKS[$roleSlug] ?? [
            ['key' => 'dashboard', 'label' => 'Overview', 'route' => 'dashboard'],
        ];

        return $this->buildSidebarMarkup($sidebarLinks, $active);
    }

    /**
     * @param  array<int, array{key: string, label: string, route: string}>  $links
     */
    private function buildSidebarMarkup(array $links, string $active): string
    {
        return (string) collect($links)->reduce(
            fn (string $markup, array $link): string => $markup.$this->renderSidebarLink($link, $active),
            '',
        );
    }

    /**
     * @param  array{key: string, label: string, route: string}  $link
     */
    private function renderSidebarLink(array $link, string $active): string
    {
        $activeClass = $active === $link['key'] ? ' is-active' : '';

        return '<a href="'.route($link['route']).'" class="dash-nav-link'.$activeClass.'">'.$link['label'].'</a>';
    }

    /**
     * @return array<string, mixed>
     */
    private function getDashboardData(string $portal, User $user): array
    {
        return [
            'portal' => $portal,
            'message' => 'Welcome to LMS',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ];
    }
}

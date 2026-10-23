<?php

namespace App\Http\Controllers\Portal;

use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\WebsiteSectionItem;
use App\Services\ActivityLogService;
use App\Services\AdminService;
use App\Services\LookupService;
use App\Services\ReportService;
use App\Services\RolePermissionService;
use Illuminate\View\View;

class AdminPortalController extends PortalController
{
    public function __construct(private AdminService $adminService) {}

    /**
     * Roles screen: the grid loads from /api/erp/roles/datatable; the popup edits the permission matrix.
     */
    public function roles(RolePermissionService $roleService): View
    {
        $permissionGroups = $roleService->permissionGroups();

        return $this->page('dashboards.admin.roles', 'admin', 'roles', [
            'permission_groups' => $permissionGroups,
            'counts' => [
                'roles' => Role::count(),
                'permissions' => $permissionGroups->flatten()->count(),
                'groups' => $permissionGroups->count(),
            ],
        ]);
    }

    /**
     * Lookups screen: the grid itself loads from /api/erp/lookups/datatable.
     */
    public function lookups(LookupService $lookupService): View
    {
        return $this->page('dashboards.admin.lookups', 'admin', 'lookups', [
            'types' => $lookupService->types(),
        ]);
    }

    /**
     * Activity log screen: the grid loads from /api/erp/activity-logs/datatable.
     */
    public function activityLog(ActivityLogService $activityLogService): View
    {
        return $this->page('dashboards.admin.activity-log', 'admin', 'activity_log', $activityLogService->filterOptions());
    }

    /**
     * Website content screens: posts (news & notices), stats, testimonials, faqs.
     */
    public function website(string $type, LookupService $lookupService): View
    {
        return $this->page('dashboards.admin.website', 'admin', 'website', [
            'type' => $type,
            'news_categories' => $lookupService->options('news_category'),
        ]);
    }

    /**
     * Website page editor (data: /api/erp/website/pages).
     */
    public function websitePages(): View
    {
        return $this->page('dashboards.admin.website-pages', 'admin', 'website', ['tones' => WebsiteSectionItem::TONES]);
    }

    public function approvals(): View
    {
        return $this->page('dashboards.admin.approvals', 'admin', 'approvals');
    }

    public function campuses(): View
    {
        return $this->page('dashboards.admin.campuses', 'admin', 'campuses');
    }

    public function numbering(): View
    {
        return $this->page('dashboards.admin.numbering', 'admin', 'numbering');
    }

    /**
     * Reports centre: the catalogue is filtered by the user's permissions; data loads from /api/erp/reports/{key}.
     */
    public function reports(ReportService $reportService): View
    {
        $user = $this->portalUser();
        $catalogue = $reportService->catalogue($user);
        abort_if($catalogue === [], 403);

        return $this->page('dashboards.admin.reports', $user->hasPermission('view-stats') ? 'admin' : 'accounts', 'reports', [
            'catalogue' => $catalogue,
            'classes' => SchoolClass::where('is_active', true)->orderBy('level_order')->get(['id', 'name']),
        ]);
    }
}

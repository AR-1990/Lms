<?php

namespace App\Http\Controllers\Portal;

use App\Services\AdminService;
use Illuminate\View\View;

class AdminPortalController extends PortalController
{
    public function __construct(private AdminService $adminService) {}

    public function users(): View
    {
        return $this->page('dashboards.admin.users', 'admin', 'users', $this->adminService->getUsersDirectory());
    }

    public function roles(): View
    {
        return $this->page('dashboards.admin.roles', 'admin', 'roles', $this->adminService->getRolesDirectory());
    }

    public function reports(): View
    {
        return $this->page('dashboards.admin.reports', 'admin', 'reports', $this->adminService->getReportsSnapshot());
    }
}

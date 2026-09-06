<?php

namespace App\Http\Controllers\Portal;

use App\Services\ParentService;
use Illuminate\View\View;

class ParentPortalController extends PortalController
{
    public function __construct(private ParentService $parentService) {}

    public function children(): View
    {
        $user = $this->portalUser();

        return $this->page('dashboards.parent.children', 'children', $this->parentService->getChildrenProfiles($user));
    }

    public function fees(): View
    {
        $user = $this->portalUser();

        return $this->page('dashboards.parent.fees', 'fees', $this->parentService->getFeeLedger($user));
    }

    public function notices(): View
    {
        $user = $this->portalUser();

        return $this->page('dashboards.parent.notices', 'notices', $this->parentService->getNotices($user));
    }
}

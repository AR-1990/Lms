<?php

namespace App\Http\Controllers\Portal;

use App\Services\AccountsService;
use Illuminate\View\View;

class AccountsPortalController extends PortalController
{
    public function __construct(private AccountsService $accountsService) {}

    public function collections(): View
    {
        $user = $this->portalUser();

        return $this->page('dashboards.accounts.collections', 'collections', $this->accountsService->getCollections($user));
    }

    public function challans(): View
    {
        $user = $this->portalUser();

        return $this->page('dashboards.accounts.challans', 'challans', $this->accountsService->getChallans($user));
    }

    public function payroll(): View
    {
        $user = $this->portalUser();

        return $this->page('dashboards.accounts.payroll', 'payroll', $this->accountsService->getPayroll($user));
    }
}

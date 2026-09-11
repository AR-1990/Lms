<?php

namespace App\Http\Controllers;

use App\Services\PortalAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private PortalAccessService $portalAccessService) {}

    /**
     * Route the signed-in user to the dashboard that matches their role.
     */
    public function index(): View|RedirectResponse
    {
        $user = auth()->user()->load('roles');
        $dashboard = $this->portalAccessService->resolveDashboard($user, session('portal'));

        if ($dashboard === null) {
            return redirect()->route('home')->with('error', 'No portal access assigned to this account.');
        }

        return view($dashboard['view'], [
            'user' => $user,
            'data' => $dashboard['data'],
            'active' => 'dashboard',
            'portal' => $dashboard['portal'],
        ]);
    }
}

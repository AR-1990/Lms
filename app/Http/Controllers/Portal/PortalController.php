<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PortalAccessService;
use Illuminate\View\View;

abstract class PortalController extends Controller
{
    protected function portalUser(): User
    {
        return auth()->user()->load('roles');
    }

    protected function page(string $view, string $portal, string $active, array $data = []): View
    {
        $user = $this->portalUser();

        return view($view, [
            'user' => $user,
            'portal' => $portal,
            'active' => $active,
            'data' => $data,
            'sidebar' => app(PortalAccessService::class)->getSidebarConfig($user, $portal, $active),
        ]);
    }
}

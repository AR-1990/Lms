<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

abstract class PortalController extends Controller
{
    protected function portalUser(): User
    {
        return auth()->user()->load('roles');
    }

    protected function page(string $view, string $active, array $data = []): View
    {
        return view($view, [
            'user' => $this->portalUser(),
            'active' => $active,
            'data' => $data,
        ]);
    }
}

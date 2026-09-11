<?php

namespace App\Http\Controllers\Portal;

use App\Http\Requests\UpdateSystemSettingsRequest;
use App\Services\SystemSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminSettingsController extends PortalController
{
    public function __construct(private SystemSettingsService $systemSettingsService) {}

    public function index(): View
    {
        return $this->page('dashboards.admin.settings', 'admin', 'settings', $this->systemSettingsService->getSettingsPageData());
    }

    public function update(UpdateSystemSettingsRequest $request): RedirectResponse
    {
        $this->systemSettingsService->updateSettings($request->validated(), $request->file('logo'));

        return redirect()
            ->route('admin.settings')
            ->with('status', 'System settings updated successfully.');
    }
}

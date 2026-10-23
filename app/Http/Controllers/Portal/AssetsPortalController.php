<?php

namespace App\Http\Controllers\Portal;

use App\Models\Account;
use App\Models\AssetCategory;
use App\Models\Employee;
use App\Models\Location;
use App\Models\PaymentMethod;
use App\Models\Vendor;
use App\Services\LookupService;
use Illuminate\View\View;

/**
 * Asset screens (data: /api/erp/assets…). Each screen gets the dropdown options it needs.
 */
class AssetsPortalController extends PortalController
{
    public function __construct(private LookupService $lookupService) {}

    public function index(): View
    {
        return $this->screen('register');
    }

    public function handovers(): View
    {
        return $this->screen('handovers');
    }

    public function repairs(): View
    {
        return $this->screen('repairs');
    }

    public function verification(): View
    {
        return $this->screen('verification');
    }

    public function depreciation(): View
    {
        return $this->screen('depreciation');
    }

    public function disposals(): View
    {
        return $this->screen('disposals');
    }

    public function categories(): View
    {
        return $this->screen('categories', ['accounts' => Account::where('is_group', false)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name'])]);
    }

    public function locations(): View
    {
        return $this->screen('locations', [
            'staff' => Employee::whereIn('status', Employee::CURRENT_STATUSES)->orderBy('first_name')->get(['id', 'first_name', 'middle_name', 'last_name'])
                ->map(fn (Employee $employee): object => (object) ['id' => $employee->id, 'name' => $employee->fullName()]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function screen(string $screen, array $data = []): View
    {
        return $this->page("dashboards.assets.{$screen}", 'admin', 'assets', [
            'categories' => AssetCategory::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'conditions' => $this->lookupService->options('asset_condition'),
            'maintenance_types' => $this->lookupService->options('maintenance_type'),
            'vendors' => Vendor::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'payment_methods' => PaymentMethod::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            ...$data,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Portal;

use App\Models\Employee;
use App\Models\FeeHead;
use App\Models\TransportRoute;
use App\Models\Vehicle;
use App\Services\LookupService;
use Illuminate\View\View;

/**
 * Transport screens (data: /api/erp/transport-routes, student-transport, vehicles).
 */
class TransportPortalController extends PortalController
{
    public function __construct(private LookupService $lookupService) {}

    public function routes(): View
    {
        return $this->screen('routes', [
            'staff' => Employee::whereIn('status', Employee::CURRENT_STATUSES)->orderBy('first_name')->get(['id', 'first_name', 'middle_name', 'last_name'])
                ->map(fn (Employee $employee): object => (object) ['id' => $employee->id, 'name' => $employee->fullName()]),
            'vehicles' => Vehicle::where('is_active', true)->orderBy('registration_no')->get(['id', 'registration_no as name']),
            'fee_heads' => FeeHead::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'areas' => $this->lookupService->options('transport_stop_area'),
        ]);
    }

    public function riders(): View
    {
        return $this->screen('riders', ['routes' => TransportRoute::where('is_active', true)->orderBy('name')->get(['id', 'name'])]);
    }

    public function vehicles(): View
    {
        return $this->screen('vehicles', ['types' => $this->lookupService->options('vehicle_type')]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function screen(string $screen, array $data = []): View
    {
        return $this->page("dashboards.transport.{$screen}", 'admin', 'transport', $data);
    }
}

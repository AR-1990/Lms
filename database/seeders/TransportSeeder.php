<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\FeeHead;
use App\Models\Lookup;
use App\Models\TransportRoute;
use App\Models\TransportStop;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

/**
 * Demo vehicles and two routes with their stops (editable under Transport). Riders are not seeded so the
 * demo students' fee challans stay as the fee module sets them. Re-runnable.
 */
class TransportSeeder extends Seeder
{
    public function run(): void
    {
        if (TransportRoute::exists()) {
            return;
        }

        $type = fn (string $name): ?int => Lookup::where('type', 'vehicle_type')->where('name', $name)->value('id');
        $area = fn (string $name): ?int => Lookup::where('type', 'transport_stop_area')->where('name', $name)->value('id');
        $staff = Employee::whereIn('status', Employee::CURRENT_STATUSES)->orderBy('id')->pluck('id');
        $feeHead = FeeHead::where('code', 'TRN')->value('id');

        $bus = Vehicle::create(['registration_no' => 'LEA-4512', 'type_id' => $type('Bus'), 'make_model' => 'Hino AK1J', 'capacity' => 40, 'insurance_expiry' => now()->addMonths(5)->toDateString(), 'fitness_expiry' => now()->addMonths(8)->toDateString(), 'route_permit_expiry' => now()->addDays(20)->toDateString(), 'is_active' => true]);
        $coaster = Vehicle::create(['registration_no' => 'LEB-7781', 'type_id' => $type('Coaster'), 'make_model' => 'Toyota Coaster', 'capacity' => 26, 'insurance_expiry' => now()->addMonths(9)->toDateString(), 'fitness_expiry' => now()->addMonths(3)->toDateString(), 'is_active' => true]);

        foreach ([
            ['Route 1 — Gulberg', 'R1', $bus->id, 450000, [['Main Boulevard', 'Gulberg', '07:05', '14:10', null], ['Liberty Chowk', 'Gulberg', '07:15', '14:00', null], ['Model Town Link Road', 'Model Town', '07:30', '13:45', 380000]]],
            ['Route 2 — Johar Town', 'R2', $coaster->id, 400000, [['Emporium Mall', 'Johar Town', '07:00', '14:15', null], ['Expo Centre', 'Johar Town', '07:12', '14:05', null], ['Township Market', 'Township', '07:25', '13:50', null]]],
        ] as $index => [$name, $code, $vehicleId, $fee, $stops]) {
            $route = TransportRoute::create([
                'name' => $name, 'code' => $code, 'vehicle_id' => $vehicleId, 'monthly_fee' => $fee, 'fee_head_id' => $feeHead,
                'driver_employee_id' => $staff[$index + 1] ?? null, 'attendant_employee_id' => $staff[$index + 3] ?? null, 'is_active' => true,
            ]);

            foreach ($stops as $position => [$stopName, $stopArea, $pickup, $drop, $override]) {
                TransportStop::create(['transport_route_id' => $route->id, 'name' => $stopName, 'area_id' => $area($stopArea), 'pickup_time' => $pickup, 'drop_time' => $drop, 'sort_order' => $position, 'fee_override' => $override]);
            }
        }
    }
}

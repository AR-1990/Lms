<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Lookup;
use App\Models\User;
use App\Services\NumberSequenceService;
use Illuminate\Database\Seeder;

/**
 * Asset categories with their ledger accounts and depreciation rules, and the school's rooms and stores
 * (master data, editable under Assets), plus demo assets with one laptop handed to the demo teacher.
 * Re-runnable: nothing is duplicated.
 */
class AssetSeeder extends Seeder
{
    public function run(NumberSequenceService $numberSequenceService): void
    {
        $account = fn (string $code): ?int => Account::where('code', $code)->value('id');
        $categories = [];

        foreach ([
            ['Furniture & Fixtures', 'FUR', '1610', 'straight_line', 120, 5],
            ['Computers & IT Equipment', 'IT', '1620', 'straight_line', 48, 10],
            ['Vehicles', 'VEH', '1630', 'reducing_balance', 96, 15],
            ['Lab & Teaching Equipment', 'LAB', '1640', 'straight_line', 84, 5],
        ] as [$name, $code, $assetAccount, $method, $life, $salvage]) {
            $categories[$code] = AssetCategory::firstOrCreate(['code' => $code], [
                'name' => $name,
                'asset_account_id' => $account($assetAccount),
                'depreciation_account_id' => $account('5500'),
                'accumulated_depreciation_account_id' => $account('1690'),
                'depreciation_method' => $method,
                'useful_life_months' => $life,
                'salvage_percent' => $salvage,
                'is_active' => true,
            ]);
        }

        $locations = [];

        foreach ([
            ['Main Store', 'STORE', 'Main Block', 'Ground', true],
            ['Admin Office', 'ADMIN', 'Main Block', 'Ground', false],
            ['Computer Lab', 'CLAB', 'Science Block', 'First', false],
            ['Physics Lab', 'PLAB', 'Science Block', 'First', false],
            ['Library', 'LIB', 'Main Block', 'First', false],
            ['Staff Room', 'STAFF', 'Main Block', 'Ground', false],
        ] as [$name, $code, $building, $floor, $isStore]) {
            $locations[$code] = Location::firstOrCreate(['code' => $code], ['name' => $name, 'building' => $building, 'floor' => $floor, 'is_store' => $isStore, 'is_active' => true]);
        }

        if (Asset::exists()) {
            return;
        }

        $good = Lookup::where('type', 'asset_condition')->where('name', 'Good')->value('id');
        $new = Lookup::where('type', 'asset_condition')->where('name', 'New')->value('id');

        foreach ([
            ['IT', 'Dell Latitude 5440 Laptop', 'Dell', 'Latitude 5440', 'STORE', 18500000, -8, $good],
            ['IT', 'Dell Latitude 5440 Laptop', 'Dell', 'Latitude 5440', 'STORE', 18500000, -8, $good],
            ['IT', 'Epson EB-X51 Projector', 'Epson', 'EB-X51', 'CLAB', 12000000, -14, $good],
            ['IT', 'HP LaserJet Pro M404', 'HP', 'M404', 'ADMIN', 9500000, -20, $good],
            ['FUR', 'Teacher Desk (Wooden)', null, null, 'STAFF', 3500000, -30, $good],
            ['LAB', 'Digital Microscope', 'Olympus', 'CX23', 'PLAB', 22000000, -3, $new],
        ] as [$code, $name, $brand, $model, $location, $cost, $monthsAgo, $condition]) {
            $category = $categories[$code];
            $purchased = now()->addMonths($monthsAgo);

            Asset::create([
                'asset_tag' => $numberSequenceService->next('asset_tag'),
                'asset_category_id' => $category->id,
                'name' => $name,
                'brand' => $brand,
                'model' => $model,
                'serial_no' => strtoupper(fake()->bothify('SN-####-????')),
                'purchase_date' => $purchased->toDateString(),
                'purchase_cost' => $cost,
                'salvage_value' => (int) round($cost * (float) $category->salvage_percent / 100),
                'current_book_value' => $cost,
                'depreciation_start_date' => $purchased->copy()->startOfMonth()->toDateString(),
                'warranty_expires_on' => $purchased->copy()->addYear()->toDateString(),
                'location_id' => $locations[$location]->id,
                'condition_id' => $condition,
                'status' => 'in_store',
            ]);
        }

        $teacher = Employee::where('user_id', User::where('email', 'teacher@lms.test')->value('id'))->first();
        $laptop = Asset::where('name', 'Dell Latitude 5440 Laptop')->orderBy('id')->first();

        if ($teacher !== null && $laptop !== null) {
            AssetAssignment::create([
                'asset_id' => $laptop->id, 'assignee_type' => 'employee', 'assignee_id' => $teacher->id,
                'assigned_on' => now()->subMonths(2)->toDateString(), 'condition_out_id' => $good,
                'issued_by' => User::where('email', 'admin@lms.test')->value('id'), 'remarks' => 'For class presentations',
            ]);
            $laptop->update(['status' => 'assigned', 'current_holder_type' => 'employee', 'current_holder_id' => $teacher->id]);
        }
    }
}

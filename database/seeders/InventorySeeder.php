<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\FeeHead;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\Location;
use App\Models\Lookup;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\NumberSequenceService;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Item categories with their ledger accounts (master data, editable under Inventory), demo items, and
 * opening stock in the Main Store posted as a stock count ("Opening Stock" → opening balance equity).
 * Re-runnable: nothing is duplicated.
 */
class InventorySeeder extends Seeder
{
    public function run(NumberSequenceService $numberSequenceService, StockService $stockService): void
    {
        $account = fn (string $code): ?int => Account::where('code', $code)->value('id');
        $categories = [];

        foreach ([
            ['Stationery', '5600', null],
            ['Cleaning Supplies', '5900', null],
            ['Lab Consumables', '5900', null],
            ['Uniforms & Books', '5900', 'UNB'],
            ['Sports Kit', '5900', null],
        ] as [$name, $expense, $feeHead]) {
            $categories[$name] = ItemCategory::firstOrCreate(['name' => $name], [
                'inventory_account_id' => $account('1500'),
                'expense_account_id' => $account($expense),
                'income_account_id' => $feeHead ? $account('4500') : null,
                'sale_fee_head_id' => $feeHead ? FeeHead::where('code', $feeHead)->value('id') : null,
                'is_active' => true,
            ]);
        }

        if (InventoryItem::exists()) {
            return;
        }

        $unit = fn (string $code): ?int => Lookup::where('type', 'unit_of_measure')->where('code', $code)->value('id');
        $items = [];

        foreach ([
            ['Stationery', 'Whiteboard Marker (Blue)', 'pcs', 6000, 0, 20, false, false, 120],
            ['Stationery', 'A4 Paper Ream (80 gsm)', 'ream', 145000, 0, 10, false, false, 40],
            ['Stationery', 'Attendance Register', 'pcs', 35000, 0, 5, false, false, 15],
            ['Cleaning Supplies', 'Floor Cleaner 5L', 'pcs', 180000, 0, 4, false, false, 12],
            ['Lab Consumables', 'Test Tubes (box of 50)', 'box', 250000, 0, 2, false, false, 6],
            ['Uniforms & Books', 'School Shirt (Size 10)', 'pcs', 90000, 140000, 10, true, false, 30],
            ['Uniforms & Books', 'English Reader Grade 5', 'pcs', 60000, 85000, 10, true, false, 25],
            ['Sports Kit', 'Football (Size 5)', 'pcs', 250000, 0, 2, false, true, 8],
        ] as [$category, $name, $unitCode, $cost, $price, $reorder, $sellable, $returnable, $opening]) {
            $items[] = [InventoryItem::create([
                'sku' => $numberSequenceService->next('sku'),
                'item_category_id' => $categories[$category]->id,
                'name' => $name,
                'unit_id' => $unit($unitCode),
                'standard_cost' => $cost,
                'sale_price' => $price,
                'reorder_level' => $reorder,
                'reorder_quantity' => $reorder * 3,
                'is_sellable' => $sellable,
                'is_returnable' => $returnable,
                'is_active' => true,
            ]), $opening, $cost];
        }

        $store = Location::where('is_store', true)->orderBy('id')->first();
        $admin = User::where('email', 'admin@lms.test')->first();

        if ($store === null || $admin === null || StockMovement::exists()) {
            return;
        }

        // Postings record who made them; sign the admin in only for this and out again afterwards.
        Auth::setUser($admin);

        try {
            ActivityLogger::quietly(fn () => $stockService->adjust([
                'location_id' => $store->id,
                'adjusted_on' => now()->toDateString(),
                'reason_id' => Lookup::where('type', 'stock_adjustment_reason')->where('name', 'Opening Stock')->value('id'),
                'remarks' => 'Opening stock',
                'items' => array_map(fn (array $row): array => ['inventory_item_id' => $row[0]->id, 'counted_qty' => $row[1], 'unit_cost' => $row[2]], $items),
            ], $admin));
        } finally {
            Auth::forgetUser();
        }
    }
}

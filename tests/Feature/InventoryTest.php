<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Employee;
use App\Models\FeeInvoice;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\JournalEntry;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\StockAdjustment;
use App\Models\StockLevel;
use App\Models\Student;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Vendor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Location $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(10, 0));
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->store = Location::where('code', 'STORE')->first();
        $this->actingAs($this->admin, 'sanctum');
    }

    private function item(string $name): InventoryItem
    {
        return InventoryItem::where('name', $name)->firstOrFail();
    }

    private function onHand(InventoryItem $item, ?Location $location = null): float
    {
        return (float) StockLevel::where('inventory_item_id', $item->id)->where('location_id', ($location ?? $this->store)->id)->value('quantity');
    }

    /**
     * @return array<int, int>
     */
    private function totals(?int $entryId): array
    {
        $entry = JournalEntry::with('lines')->findOrFail($entryId);

        return [(int) $entry->lines->sum('debit'), (int) $entry->lines->sum('credit')];
    }

    private function order(): PurchaseOrder
    {
        $id = $this->postJson('/api/erp/purchase-orders', [
            'vendor_id' => Vendor::query()->value('id'),
            'location_id' => $this->store->id,
            'order_date' => '2026-10-10',
            'items' => [
                ['inventory_item_id' => $this->item('Whiteboard Marker (Blue)')->id, 'quantity' => 100, 'unit_cost' => 9000],
                ['asset_category_id' => AssetCategory::where('code', 'IT')->value('id'), 'description' => 'Epson Projector', 'quantity' => 2, 'unit_cost' => 11000000],
            ],
        ])->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.total', 100 * 9000 + 2 * 11000000)->json('data.id');

        return PurchaseOrder::findOrFail($id);
    }

    public function test_receiving_an_approved_order_raises_stock_creates_assets_and_owes_the_vendor(): void
    {
        $order = $this->order();
        $lines = $order->items()->orderBy('sort_order')->get();
        $marker = $this->item('Whiteboard Marker (Blue)');
        $before = $this->onHand($marker);

        $this->postJson('/api/erp/goods-receipts', ['purchase_order_id' => $order->id, 'received_on' => '2026-10-12', 'location_id' => $this->store->id, 'items' => [['purchase_order_item_id' => $lines[0]->id, 'quantity' => 10]]])
            ->assertUnprocessable();

        $this->postJson("/api/erp/purchase-orders/{$order->id}/approve")->assertOk()->assertJsonPath('data.status', 'approved');

        $receipt = $this->postJson('/api/erp/goods-receipts', [
            'purchase_order_id' => $order->id, 'received_on' => '2026-10-12', 'location_id' => $this->store->id, 'vendor_invoice_no' => 'INV-77',
            'items' => [['purchase_order_item_id' => $lines[0]->id, 'quantity' => 60], ['purchase_order_item_id' => $lines[1]->id, 'quantity' => 2]],
        ])->assertCreated()->json('data');

        $this->assertSame($before + 60, $this->onHand($marker));
        $this->assertSame(2, Asset::where('invoice_no', $receipt['grn_no'])->where('purchase_cost', 11000000)->count());
        $this->assertSame([60 * 9000 + 22000000, 60 * 9000 + 22000000], $this->totals($receipt['journal_entry_id']));
        $this->assertSame('partially_received', $order->fresh()->status);

        $this->postJson('/api/erp/goods-receipts', ['purchase_order_id' => $order->id, 'received_on' => '2026-10-13', 'location_id' => $this->store->id, 'items' => [['purchase_order_item_id' => $lines[0]->id, 'quantity' => 41]]])
            ->assertUnprocessable();
        $this->postJson('/api/erp/goods-receipts', ['purchase_order_id' => $order->id, 'received_on' => '2026-10-13', 'location_id' => $this->store->id, 'items' => [['purchase_order_item_id' => $lines[0]->id, 'quantity' => 40]]])
            ->assertCreated();
        $this->assertSame('received', $order->fresh()->status);
        $this->postJson("/api/erp/purchase-orders/{$order->id}/cancel", ['reason' => 'Changed our mind'])->assertUnprocessable();
    }

    public function test_issuing_and_returning_stock_moves_it_at_average_cost(): void
    {
        $football = $this->item('Football (Size 5)');
        $employee = Employee::whereNotNull('user_id')->first();

        $issue = $this->postJson('/api/erp/stock-issues', [
            'issued_on' => '2026-10-14', 'location_id' => $this->store->id, 'recipient_type' => 'employee', 'recipient_id' => $employee->id, 'purpose' => 'Sports period',
            'items' => [['inventory_item_id' => $football->id, 'quantity' => 3, 'due_back_on' => '2026-10-14']],
        ])->assertCreated()->assertJsonPath('data.recipient_name', $employee->fullName())->json('data');

        $this->assertSame(5.0, $this->onHand($football));
        $this->assertSame([3 * 250000, 3 * 250000], $this->totals($issue['journal_entry_id']));

        $this->postJson('/api/erp/stock-issues', ['issued_on' => '2026-10-14', 'location_id' => $this->store->id, 'recipient_type' => 'employee', 'recipient_id' => $employee->id, 'items' => [['inventory_item_id' => $football->id, 'quantity' => 50]]])
            ->assertUnprocessable();

        $this->postJson("/api/erp/stock-issues/{$issue['id']}/return", ['returned_on' => '2026-10-15', 'items' => [['stock_issue_item_id' => $issue['items'][0]['id'], 'quantity' => 4]]])->assertUnprocessable();
        $this->postJson("/api/erp/stock-issues/{$issue['id']}/return", ['returned_on' => '2026-10-15', 'items' => [['stock_issue_item_id' => $issue['items'][0]['id'], 'quantity' => 3]]])
            ->assertOk()
            ->assertJsonPath('data.status', 'returned');
        $this->assertSame(8.0, $this->onHand($football));
    }

    public function test_selling_to_a_student_puts_the_items_on_a_fee_challan(): void
    {
        $student = Student::whereHas('currentEnrollment')->first();
        $shirt = $this->item('School Shirt (Size 10)');

        $issue = $this->postJson('/api/erp/stock-issues', [
            'issued_on' => '2026-10-14', 'location_id' => $this->store->id, 'recipient_type' => 'student', 'recipient_id' => $student->id, 'is_sale' => true, 'due_date' => '2026-10-20',
            'items' => [['inventory_item_id' => $shirt->id, 'quantity' => 2]],
        ])->assertCreated()->json('data');

        $invoice = FeeInvoice::findOrFail($issue['fee_invoice_id']);
        $this->assertSame($student->id, $invoice->student_id);
        $this->assertSame(2 * 140000, (int) $invoice->total);
        $this->assertSame(28.0, $this->onHand($shirt));

        $this->postJson('/api/erp/stock-issues', ['issued_on' => '2026-10-14', 'location_id' => $this->store->id, 'recipient_type' => 'student', 'recipient_id' => $student->id, 'is_sale' => true, 'items' => [['inventory_item_id' => $this->item('Floor Cleaner 5L')->id, 'quantity' => 1]]])
            ->assertUnprocessable();
        $this->postJson('/api/erp/stock-issues', ['issued_on' => '2026-10-14', 'location_id' => $this->store->id, 'recipient_type' => 'employee', 'recipient_id' => Employee::query()->value('id'), 'is_sale' => true, 'items' => [['inventory_item_id' => $shirt->id, 'quantity' => 1]]])
            ->assertUnprocessable();
    }

    public function test_transfers_move_stock_between_stores(): void
    {
        $annex = Location::create(['name' => 'Annex Store', 'is_store' => true, 'is_active' => true]);
        $paper = $this->item('A4 Paper Ream (80 gsm)');

        $this->postJson('/api/erp/stock-transfers', ['from_location_id' => $this->store->id, 'to_location_id' => $annex->id, 'transferred_on' => '2026-10-14', 'items' => [['inventory_item_id' => $paper->id, 'quantity' => 15]]])
            ->assertCreated();

        $this->assertSame(25.0, $this->onHand($paper));
        $this->assertSame(15.0, $this->onHand($paper, $annex));
        $this->assertSame(145000, (int) StockLevel::where('inventory_item_id', $paper->id)->where('location_id', $annex->id)->value('average_cost'));
    }

    public function test_a_stock_count_posts_the_shortage_and_warns_when_stock_runs_low(): void
    {
        $register = $this->item('Attendance Register');

        $count = $this->postJson('/api/erp/stock-adjustments', ['location_id' => $this->store->id, 'adjusted_on' => '2026-10-14', 'items' => [['inventory_item_id' => $register->id, 'counted_qty' => 4]]])
            ->assertCreated()
            ->json('data');

        $this->assertSame(4.0, $this->onHand($register));
        $this->assertSame([11 * 35000, 11 * 35000], $this->totals(StockAdjustment::findOrFail($count['id'])->journal_entry_id));
        $this->assertTrue(UserNotification::where('user_id', $this->admin->id)->where('event_key', 'stock_low')->exists());

        $this->actingAs(User::where('email', 'accounts@lms.test')->first(), 'sanctum')
            ->postJson('/api/erp/stock-adjustments', ['location_id' => $this->store->id, 'adjusted_on' => '2026-10-14', 'items' => [['inventory_item_id' => $register->id, 'counted_qty' => 1]]])
            ->assertForbidden();
    }

    public function test_forms_save_when_optional_numbers_are_left_empty(): void
    {
        $category = ItemCategory::query()->value('id');
        $assetCategory = AssetCategory::query()->value('id');

        $this->postJson('/api/erp/inventory/items', ['name' => 'Glue Stick', 'item_category_id' => $category, 'reorder_level' => null, 'reorder_quantity' => null, 'standard_cost' => null, 'sale_price' => null])
            ->assertCreated();
        $this->postJson('/api/erp/purchase-orders', ['vendor_id' => Vendor::query()->value('id'), 'order_date' => '2026-10-10', 'tax' => null, 'discount' => null, 'items' => [['inventory_item_id' => $this->item('Whiteboard Marker (Blue)')->id, 'quantity' => 1, 'unit_cost' => 100]]])
            ->assertCreated();
        $this->postJson('/api/erp/asset-categories', ['name' => 'Musical Instruments', 'depreciation_method' => 'straight_line', 'useful_life_months' => 60, 'salvage_percent' => null])
            ->assertCreated();
        $assetId = $this->postJson('/api/erp/assets', ['name' => 'Donated Piano', 'asset_category_id' => $assetCategory, 'purchase_cost' => null])->assertCreated()->json('data.id');
        $this->postJson("/api/erp/assets/{$assetId}/repairs", ['reported_on' => '2026-10-14', 'cost' => null])->assertCreated();
    }

    public function test_staff_without_inventory_permissions_are_kept_out(): void
    {
        $teacher = User::where('email', 'teacher@lms.test')->first();

        $this->actingAs($teacher, 'sanctum')->getJson('/api/erp/inventory/items/datatable?draw=1')->assertForbidden()->assertJsonPath('success', false);
        $this->postJson('/api/erp/stock-issues', [])->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.inventory.index'))->assertOk();
        $this->actingAs($teacher)->get(route('admin.inventory.index'))->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetMaintenance;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\Location;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\UserNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(10, 0));
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->teacher = User::where('email', 'teacher@lms.test')->first();
    }

    private function laptopCategory(): AssetCategory
    {
        return AssetCategory::where('code', 'IT')->first();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function register(array $overrides = []): Asset
    {
        $id = $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/assets', [
            'asset_category_id' => $this->laptopCategory()->id,
            'name' => 'Lenovo ThinkPad E14',
            'purchase_date' => '2026-08-10',
            'purchase_cost' => 4800000,
            'location_id' => Location::where('code', 'STORE')->value('id'),
            ...$overrides,
        ])->assertCreated()->assertJsonPath('success', true)->json('data.id');

        return Asset::findOrFail($id);
    }

    /**
     * @return array<int, int>
     */
    private function entryTotals(int $entryId): array
    {
        $entry = JournalEntry::with('lines')->findOrFail($entryId);

        return [(int) $entry->lines->sum('debit'), (int) $entry->lines->sum('credit')];
    }

    public function test_registering_an_asset_numbers_it_and_can_post_the_purchase(): void
    {
        $cash = PaymentMethod::where('code', 'CASH')->first();
        $asset = $this->register(['post_purchase' => true, 'payment_method_id' => $cash->id]);

        $this->assertStringStartsWith('AST-', $asset->asset_tag);
        $this->assertSame(4800000, $asset->current_book_value);
        $this->assertSame(480000, $asset->salvage_value);
        $this->assertNotNull($asset->journal_entry_id);
        $this->assertSame([4800000, 4800000], $this->entryTotals($asset->journal_entry_id));

        $this->putJson("/api/erp/assets/{$asset->id}", ['asset_category_id' => $asset->asset_category_id, 'name' => $asset->name, 'purchase_cost' => 5000000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('purchase_cost', 'errors.fields');
    }

    public function test_assets_are_handed_out_taken_back_and_shown_to_the_holder(): void
    {
        $asset = $this->register();
        $employee = Employee::where('user_id', $this->teacher->id)->first();

        $this->postJson("/api/erp/assets/{$asset->id}/assign", ['assignee_type' => 'employee', 'assignee_id' => $employee->id, 'assigned_on' => '2026-10-01', 'expected_return_on' => '2026-10-10'])
            ->assertOk()
            ->assertJsonPath('data.status', 'assigned')
            ->assertJsonPath('data.holder_name', $employee->fullName());
        $this->postJson("/api/erp/assets/{$asset->id}/assign", ['assignee_type' => 'employee', 'assignee_id' => $employee->id, 'assigned_on' => '2026-10-02'])->assertUnprocessable();
        $this->assertTrue(UserNotification::where('user_id', $this->teacher->id)->where('event_key', 'asset_assigned')->exists());

        $this->getJson('/api/erp/asset-assignments/datatable?draw=1&open=1')->assertOk()->assertJsonFragment(['is_overdue' => true]);
        $this->actingAs($this->teacher, 'sanctum')->getJson('/api/erp/my-assets')->assertOk()->assertJsonFragment(['asset_tag' => $asset->asset_tag]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/erp/assets/{$asset->id}/return", ['returned_on' => '2026-10-12', 'location_id' => Location::where('code', 'STORE')->value('id')])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_store')
            ->assertJsonPath('data.holder_name', null);
        $this->actingAs($this->teacher, 'sanctum')->getJson('/api/erp/my-assets')->assertOk()->assertJsonMissing(['asset_tag' => $asset->asset_tag]);
    }

    public function test_a_repair_takes_the_asset_out_and_posts_its_cost(): void
    {
        $asset = $this->register();
        $jobId = $this->postJson("/api/erp/assets/{$asset->id}/repairs", ['reported_on' => '2026-10-05', 'description' => 'Screen flicker'])->assertCreated()->json('data.id');
        $this->assertSame('in_repair', $asset->fresh()->status);

        $this->postJson("/api/erp/asset-maintenances/{$jobId}/complete", ['completed_on' => '2026-10-08', 'cost' => 350000, 'payment_method_id' => PaymentMethod::where('code', 'CASH')->value('id')])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertSame('in_store', $asset->fresh()->status);
        $entryId = AssetMaintenance::findOrFail($jobId)->journal_entry_id;
        $this->assertSame([350000, 350000], $this->entryTotals($entryId));
        $this->assertTrue(JournalEntry::findOrFail($entryId)->lines()->where('account_id', Account::where('code', '5700')->value('id'))->exists());
    }

    public function test_monthly_depreciation_posts_once_and_can_be_reversed(): void
    {
        $asset = $this->register();
        $monthly = (int) round((4800000 - 480000) / 48);

        $preview = $this->getJson('/api/erp/asset-depreciation/preview?month=2026-10')->assertOk()->json('data');
        $this->assertContains($asset->id, array_column($preview['rows'], 'asset_id'));

        $this->postJson('/api/erp/asset-depreciation/run', ['month' => '2026-10'])->assertOk();
        $this->assertSame(4800000 - $monthly, $asset->fresh()->current_book_value);
        $this->postJson('/api/erp/asset-depreciation/run', ['month' => '2026-10'])->assertUnprocessable();
        $this->postJson('/api/erp/asset-depreciation/run', ['month' => '2026-12'])->assertUnprocessable();

        $this->postJson('/api/erp/asset-depreciation/reverse', ['month' => '2026-10', 'reason' => 'Wrong month posted'])->assertOk();
        $this->assertSame(4800000, $asset->fresh()->current_book_value);
        $this->assertSoftDeleted('asset_depreciations', ['asset_id' => $asset->id]);
    }

    public function test_disposal_books_the_gain_or_loss_and_needs_an_approver(): void
    {
        $asset = $this->register();
        $asset->update(['current_book_value' => 3000000]);
        $bank = PaymentMethod::where('code', 'BANK')->first();

        $this->postJson("/api/erp/assets/{$asset->id}/dispose", ['disposed_on' => '2026-10-14', 'method' => 'sold', 'sale_amount' => 2500000, 'payment_method_id' => $bank->id, 'reason' => 'Sold to staff member'])
            ->assertOk()
            ->assertJsonPath('data.status', 'disposed');

        $entry = JournalEntry::with('lines')->latest('id')->first();
        $this->assertSame([4800000, 4800000], $this->entryTotals($entry->id));
        $this->assertSame(500000, (int) $entry->lines->firstWhere('account_id', Account::where('code', '5810')->value('id'))?->debit);

        $other = $this->register();
        $accountant = User::where('email', 'accounts@lms.test')->first();
        $accountant->roles()->first()->givePermissionTo('manage-assets');
        $this->actingAs($accountant->fresh(), 'sanctum')
            ->postJson("/api/erp/assets/{$other->id}/dispose", ['disposed_on' => '2026-10-14', 'method' => 'scrapped', 'reason' => 'Broken beyond repair'])
            ->assertForbidden();
    }

    public function test_an_asset_with_history_is_disposed_not_deleted(): void
    {
        $asset = $this->register();
        $employee = Employee::where('user_id', $this->teacher->id)->first();
        $this->postJson("/api/erp/assets/{$asset->id}/assign", ['assignee_type' => 'employee', 'assignee_id' => $employee->id, 'assigned_on' => '2026-10-01'])->assertOk();

        $this->deleteJson("/api/erp/assets/{$asset->id}", ['reason' => 'Entered twice'])->assertUnprocessable();

        $fresh = $this->register(['name' => 'Typo entry']);
        $this->deleteJson("/api/erp/assets/{$fresh->id}", ['reason' => 'Entered twice'])->assertOk();
        $this->assertSoftDeleted($fresh);
    }

    public function test_verification_marks_missing_assets_lost(): void
    {
        $store = Location::where('code', 'STORE')->first();
        $audit = $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/asset-audits', ['audit_date' => '2026-10-14', 'location_id' => $store->id])->assertCreated()->json('data');
        $this->assertNotEmpty($audit['items']);
        $missing = $audit['items'][0];

        $this->putJson("/api/erp/asset-audits/{$audit['id']}", [
            'items' => collect($audit['items'])->map(fn (array $item): array => ['id' => $item['id'], 'found' => $item['id'] !== $missing['id']])->all(),
            'complete' => true,
            'mark_missing_lost' => true,
        ])->assertOk()->assertJsonPath('data.status', 'completed');

        $this->assertSame('lost', Asset::where('asset_tag', $missing['asset_tag'])->value('status'));
        $this->putJson("/api/erp/asset-audits/{$audit['id']}", ['items' => []])->assertUnprocessable();
    }

    public function test_staff_without_asset_permissions_cannot_manage_assets(): void
    {
        $this->actingAs($this->teacher, 'sanctum')->getJson('/api/erp/assets/datatable?draw=1')->assertForbidden()->assertJsonPath('success', false);
        $this->postJson('/api/erp/asset-categories', ['name' => 'Toys', 'depreciation_method' => 'none'])->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.assets.index'))->assertOk();
        $this->actingAs($this->teacher)->get(route('admin.assets.index'))->assertForbidden();
    }
}

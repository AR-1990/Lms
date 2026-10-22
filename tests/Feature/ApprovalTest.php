<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\FeeHead;
use App\Models\Lookup;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->accountant = User::where('email', 'accounts@lms.test')->first();
    }

    private function storekeeper(): User
    {
        Role::create(['name' => 'Store Keeper', 'slug' => 'store-keeper'])->syncPermissions(['manage-purchases']);
        $user = User::factory()->create(['email' => 'store@lms.test']);
        $user->syncRoles(['store-keeper']);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function purchasePayload(): array
    {
        return [
            'purpose' => 'Printer paper for the exam season',
            'items' => [
                ['item_name' => 'A4 paper', 'category_id' => Lookup::where('type', 'purchase_category')->value('id'), 'quantity' => 10, 'unit_id' => Lookup::where('type', 'unit_of_measure')->value('id'), 'estimated_unit_price' => 150000],
                ['item_name' => 'Toner', 'quantity' => 2, 'estimated_unit_price' => 900000],
            ],
        ];
    }

    public function test_an_administrator_deletes_at_once_and_the_register_records_who_approved(): void
    {
        $vendor = Vendor::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/erp/ledger/vendors/'.$vendor->id, ['reason' => 'Duplicate vendor'])
            ->assertOk()
            ->assertJsonPath('message', 'Vendor deleted.');

        $this->assertSoftDeleted($vendor);
        $this->assertDatabaseHas('approval_requests', [
            'request_type' => 'delete', 'subject_id' => $vendor->id, 'status' => 'approved',
            'requested_by' => $this->admin->id, 'decided_by' => $this->admin->id, 'reason' => 'Duplicate vendor',
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'delete_approved', 'subject_id' => $vendor->id, 'user_id' => $this->admin->id]);
    }

    public function test_other_users_need_a_reason_and_the_record_stays_until_an_administrator_approves(): void
    {
        $vendor = Vendor::factory()->create();

        $this->actingAs($this->accountant, 'sanctum')
            ->deleteJson('/api/erp/ledger/vendors/'.$vendor->id)
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.reason.0', 'Give a reason; an administrator approves every deletion.');

        $pending = $this->actingAs($this->accountant, 'sanctum')
            ->deleteJson('/api/erp/ledger/vendors/'.$vendor->id, ['reason' => 'Vendor closed down'])
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.requested_by_name', 'Nadia Hussain')
            ->json('data.id');

        $this->assertNotSoftDeleted($vendor);
        $this->assertDatabaseHas('activity_logs', ['action' => 'delete_requested', 'subject_id' => $vendor->id, 'user_id' => $this->accountant->id]);

        $this->actingAs($this->accountant, 'sanctum')->deleteJson('/api/erp/ledger/vendors/'.$vendor->id, ['reason' => 'Again'])->assertUnprocessable();
        $this->actingAs($this->accountant, 'sanctum')->postJson("/api/erp/approvals/{$pending}/approve")->assertForbidden();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/erp/approvals/{$pending}/approve", ['decision_note' => 'OK'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.decided_by_name', 'Super Administrator');

        $this->assertSoftDeleted($vendor);
        $this->assertDatabaseHas('activity_logs', ['action' => 'delete_approved', 'subject_id' => $vendor->id, 'user_id' => $this->admin->id]);
    }

    public function test_rejection_needs_a_note_and_keeps_the_record(): void
    {
        $vendor = Vendor::factory()->create();
        $pending = $this->actingAs($this->accountant, 'sanctum')->deleteJson('/api/erp/ledger/vendors/'.$vendor->id, ['reason' => 'Not needed'])->json('data.id');

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/approvals/{$pending}/reject")->assertUnprocessable();
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/erp/approvals/{$pending}/reject", ['decision_note' => 'Still supplying the canteen'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertNotSoftDeleted($vendor);
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/approvals/{$pending}/approve")->assertUnprocessable();
    }

    public function test_a_record_still_in_use_is_refused_at_request_time(): void
    {
        $tuition = FeeHead::where('code', 'TUI')->first();

        $this->actingAs($this->accountant, 'sanctum')
            ->deleteJson('/api/erp/fees/heads/'.$tuition->id, ['reason' => 'Renaming'])
            ->assertUnprocessable();

        $this->assertNotSoftDeleted($tuition);
        $this->assertSame(0, ApprovalRequest::count());
    }

    public function test_requesters_see_only_their_own_requests_and_approvers_see_all(): void
    {
        $this->actingAs($this->accountant, 'sanctum')->deleteJson('/api/erp/ledger/vendors/'.Vendor::factory()->create()->id, ['reason' => 'One']);
        $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/erp/ledger/vendors/'.Vendor::factory()->create()->id);

        $this->actingAs($this->accountant, 'sanctum')->getJson('/api/erp/approvals')->assertOk()->assertJsonPath('data.meta.total', 1);
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/approvals?status=pending')->assertOk()->assertJsonPath('data.meta.total', 1);
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/approvals')->assertOk()->assertJsonPath('data.meta.total', 2);
    }

    public function test_purchase_requests_wait_for_approval_and_approved_ones_cannot_be_deleted(): void
    {
        $storekeeper = $this->storekeeper();

        $purchase = $this->actingAs($storekeeper, 'sanctum')
            ->postJson('/api/erp/purchase-requests', $this->purchasePayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.estimated_total', 3300000)
            ->assertJsonPath('data.items.0.line_total', 1500000)
            ->json('data');

        $approval = ApprovalRequest::where('request_type', 'purchase')->first();
        $this->assertSame(3300000, $approval->amount);
        $this->assertSame('pending', $approval->status);

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/approvals/{$approval->id}/approve")->assertOk();
        $this->assertSame('approved', PurchaseRequest::find($purchase['id'])->status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'purchase_approved', 'user_id' => $this->admin->id]);

        $this->actingAs($storekeeper, 'sanctum')->putJson('/api/erp/purchase-requests/'.$purchase['id'], $this->purchasePayload())->assertUnprocessable();
        $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/erp/purchase-requests/'.$purchase['id'])->assertUnprocessable();
    }

    public function test_an_administrators_own_purchase_request_is_approved_at_once(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/purchase-requests', $this->purchasePayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.decided_by_name', 'Super Administrator');

        $this->actingAs($this->accountant, 'sanctum')->postJson('/api/erp/purchase-requests', $this->purchasePayload())->assertForbidden();
    }

    public function test_approval_and_purchase_screens_render_for_permitted_users_only(): void
    {
        $teacher = User::where('email', 'teacher@lms.test')->first();

        $this->actingAs($this->admin)->get(route('admin.approvals'))->assertOk()->assertSee('data-approvals', false)->assertSee('js/admin-approvals.js', false);
        $this->actingAs($this->admin)->get(route('admin.hr.purchases'))->assertOk()->assertSee('data-purchases', false);
        $this->actingAs($teacher)->get(route('admin.approvals'))->assertForbidden();
        $this->actingAs($teacher)->get(route('admin.hr.purchases'))->assertForbidden();
    }
}

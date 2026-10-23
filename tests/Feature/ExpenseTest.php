<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InventoryItem;
use App\Models\JournalEntry;
use App\Models\Location;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Vendor;
use App\Services\JournalService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(10, 0));
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->accountant = User::where('email', 'accounts@lms.test')->first();
    }

    private function method(string $code): PaymentMethod
    {
        return PaymentMethod::where('code', $code)->firstOrFail();
    }

    /**
     * @return array<int, int>
     */
    private function totals(int $entryId): array
    {
        $entry = JournalEntry::with('lines')->findOrFail($entryId);

        return [(int) $entry->lines->sum('debit'), (int) $entry->lines->sum('credit')];
    }

    public function test_an_expense_is_approved_by_an_approver_paid_and_reversed(): void
    {
        $utilities = ExpenseCategory::where('name', 'Utilities')->first();

        $expense = $this->actingAs($this->accountant, 'sanctum')->postJson('/api/erp/expenses', [
            'expense_category_id' => $utilities->id, 'expense_date' => '2026-10-10', 'amount' => 2000000, 'tax_amount' => null, 'description' => 'Gas bill',
        ])->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.total', 2000000)->json('data');

        $this->postJson("/api/erp/expenses/{$expense['id']}/pay", ['paid_on' => '2026-10-12', 'payment_method_id' => $this->method('CASH')->id])->assertUnprocessable();
        $this->postJson("/api/erp/expenses/{$expense['id']}/approve")->assertForbidden();

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/expenses/{$expense['id']}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
        $this->putJson("/api/erp/expenses/{$expense['id']}", ['expense_category_id' => $utilities->id, 'expense_date' => '2026-10-10', 'amount' => 1])->assertUnprocessable();

        $paid = $this->actingAs($this->accountant, 'sanctum')
            ->postJson("/api/erp/expenses/{$expense['id']}/pay", ['paid_on' => '2026-10-12', 'payment_method_id' => $this->method('CASH')->id, 'reference_no' => 'K-101'])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->json('data');

        $entry = JournalEntry::with('lines')->findOrFail($paid['journal_entry_id']);
        $this->assertSame([2000000, 2000000], $this->totals($entry->id));
        $this->assertSame(2000000, (int) $entry->lines->firstWhere('account_id', $utilities->expense_account_id)->debit);

        $this->postJson("/api/erp/expenses/{$expense['id']}/cancel", ['reason' => 'Billed twice by mistake'])->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame(JournalEntry::STATUS_REVERSED, $entry->fresh()->status);
    }

    public function test_the_bill_scan_is_attached_to_the_expense(): void
    {
        Storage::fake('local');
        Storage::fake('private');
        $expense = Expense::where('status', 'draft')->first();

        $this->actingAs($this->accountant, 'sanctum')
            ->post('/api/erp/attachments', ['owner' => 'expenses', 'owner_id' => $expense->id, 'file' => UploadedFile::fake()->create('bill.pdf', 120, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertCreated();

        $this->getJson("/api/erp/attachments?owner=expenses&owner_id={$expense->id}")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_vendors_are_paid_only_what_they_are_owed(): void
    {
        $vendor = Vendor::query()->first();
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/goods-receipts', [
            'vendor_id' => $vendor->id, 'received_on' => '2026-10-10', 'location_id' => Location::where('code', 'STORE')->value('id'),
            'items' => [['inventory_item_id' => InventoryItem::query()->value('id'), 'quantity' => 10, 'unit_cost' => 50000]],
        ])->assertCreated();

        $this->getJson("/api/erp/vendors/{$vendor->id}/owed")->assertOk()->assertJsonPath('data.owed', 500000);
        $this->postJson('/api/erp/vendor-payments', ['vendor_id' => $vendor->id, 'paid_on' => '2026-10-12', 'amount' => 600000, 'payment_method_id' => $this->method('BANK')->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount', 'errors.fields');

        $payment = $this->postJson('/api/erp/vendor-payments', ['vendor_id' => $vendor->id, 'paid_on' => '2026-10-12', 'amount' => 300000, 'payment_method_id' => $this->method('BANK')->id, 'reference_no' => 'CHQ-55'])
            ->assertCreated()
            ->json('data');
        $this->assertSame([300000, 300000], $this->totals($payment['journal_entry_id']));
        $this->getJson("/api/erp/vendors/{$vendor->id}/owed")->assertJsonPath('data.owed', 200000);

        $this->postJson("/api/erp/vendor-payments/{$payment['id']}/reverse", ['reason' => 'Cheque was stopped'])->assertOk()->assertJsonPath('data.status', 'reversed');
        $this->getJson("/api/erp/vendors/{$vendor->id}/owed")->assertJsonPath('data.owed', 500000);
    }

    public function test_a_statement_is_reconciled_when_the_cleared_lines_agree(): void
    {
        $bank = BankAccount::firstOrFail();
        $bankAccountId = $bank->account_id;
        $income = Account::where('code', '4900')->value('id');
        $journal = app(JournalService::class);
        $deposit = $journal->record('RV', '2026-10-05', 'Donation', [['account_id' => $bankAccountId, 'debit' => 1000000], ['account_id' => $income, 'credit' => 1000000]]);
        $journal->record('PV', '2026-10-08', 'Cheque to plumber', [['account_id' => Account::where('code', '5700')->value('id'), 'debit' => 250000], ['account_id' => $bankAccountId, 'credit' => 250000]]);

        $sheet = $this->actingAs($this->accountant, 'sanctum')
            ->postJson('/api/erp/bank-reconciliations', ['bank_account_id' => $bank->id, 'statement_date' => '2026-10-10', 'statement_balance' => 1000000])
            ->assertCreated()
            ->json('data');
        $this->assertSame(750000, $sheet['book_balance']);
        $this->assertCount(2, $sheet['lines']);

        $this->postJson("/api/erp/bank-reconciliations/{$sheet['id']}/complete")->assertUnprocessable();

        $depositLine = collect($sheet['lines'])->firstWhere('debit', 1000000);
        $saved = $this->putJson("/api/erp/bank-reconciliations/{$sheet['id']}", ['cleared' => [['journal_line_id' => $depositLine['id']]]])->assertOk()->json('data');
        $this->assertSame(0, $saved['difference']);
        $this->assertSame(250000, $saved['uncleared_payments']);

        $this->postJson("/api/erp/bank-reconciliations/{$sheet['id']}/complete")->assertOk()->assertJsonPath('data.status', 'completed');
        $this->postJson('/api/erp/bank-reconciliations', ['bank_account_id' => $bank->id, 'statement_date' => '2026-10-09', 'statement_balance' => 0])->assertUnprocessable();

        $next = $this->postJson('/api/erp/bank-reconciliations', ['bank_account_id' => $bank->id, 'statement_date' => '2026-10-14', 'statement_balance' => 750000])->assertCreated()->json('data');
        $this->assertSame([250000], array_column($next['lines'], 'credit'));
        $this->assertNotContains($deposit->lines()->where('account_id', $bankAccountId)->value('id'), array_column($next['lines'], 'id'));
    }

    public function test_staff_without_accounts_permissions_are_kept_out(): void
    {
        $teacher = User::where('email', 'teacher@lms.test')->first();

        $this->actingAs($teacher, 'sanctum')->getJson('/api/erp/expenses/datatable?draw=1')->assertForbidden()->assertJsonPath('success', false);
        $this->getJson('/api/erp/bank-reconciliations/datatable?draw=1')->assertForbidden();
        $this->actingAs($this->accountant)->get(route('accounts.expenses'))->assertOk();
        $this->actingAs($this->accountant)->get(route('accounts.bank-reconciliation'))->assertOk();
        $this->actingAs($teacher)->get(route('accounts.expenses'))->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Lookup;
use App\Models\NumberSequence;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use App\Models\VoucherType;
use App\Services\JournalService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $accountant;

    /**
     * Set up the seeded chart of accounts, FY 2026-27 and voucher types.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 22));
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->accountant = User::where('email', 'accounts@lms.test')->first();
    }

    private function account(string $code): Account
    {
        return Account::where('code', $code)->firstOrFail();
    }

    /**
     * @param  array<int, array{0: string, 1: int, 2: int}>  $lines  [account code, debit, credit]
     * @return array<string, mixed>
     */
    private function voucher(array $lines, string $date = '2026-10-20', string $type = 'JV'): array
    {
        return [
            'voucher_type_id' => VoucherType::where('code', $type)->value('id'),
            'entry_date' => $date,
            'narration' => 'Test voucher',
            'lines' => array_map(fn (array $line): array => ['account_id' => $this->account($line[0])->id, 'debit' => $line[1], 'credit' => $line[2]], $lines),
        ];
    }

    public function test_seeded_chart_is_a_valid_tree(): void
    {
        $this->assertSame('Bank Accounts', $this->account('1210')->parent->name);
        $this->assertTrue($this->account('1000')->is_group);
        $this->assertTrue($this->account('1300')->is_system);
        $this->assertSame(1, FiscalYear::where('is_current', true)->count());
        $this->assertSame('1100', PaymentMethod::where('code', 'CASH')->first()->account->code);
    }

    public function test_accounts_screens_render_and_follow_permissions(): void
    {
        foreach (['chart', 'vouchers', 'trial-balance', 'fiscal-years', 'voucher-types', 'payment-methods', 'bank-accounts', 'vendors'] as $screen) {
            $this->actingAs($this->admin)->get(route('accounts.'.$screen))->assertOk();
        }

        $this->actingAs($this->admin)
            ->get(route('accounts.ledger', $this->account('1100')))
            ->assertOk()
            ->assertSee('1100 Cash in Hand')
            ->assertSee(route('erp.ledger.accounts.ledger', $this->account('1100')), false);

        $this->actingAs($this->accountant)
            ->get(route('accounts.vouchers'))
            ->assertOk()
            ->assertSee('data-can-post="1"', false)
            ->assertSee('<option value="'.$this->account('1100')->id.'">1100 Cash in Hand</option>', false)
            ->assertDontSee('>1000 Assets</option>', false);

        $teacher = User::where('email', 'teacher@lms.test')->first();
        $this->actingAs($teacher)->get(route('accounts.chart'))->assertForbidden();
        $this->actingAs($teacher)->get(route('accounts.vendors'))->assertForbidden();
    }

    public function test_view_only_role_sees_the_chart_without_edit_tabs_or_buttons(): void
    {
        $auditor = User::factory()->create();
        $role = Role::create(['name' => 'Auditor', 'slug' => 'auditor']);
        $role->syncPermissions(['view-ledger']);
        $auditor->assignRole('auditor');

        $this->actingAs($auditor)
            ->get(route('accounts.chart'))
            ->assertOk()
            ->assertSee('data-can-manage="0"', false)
            ->assertDontSee('data-resource-create', false)
            ->assertDontSee(route('accounts.vendors'), false)
            ->assertSee(route('accounts.trial-balance'), false);

        $this->actingAs($auditor)->get(route('accounts.vendors'))->assertForbidden();
        $this->actingAs($auditor, 'sanctum')->postJson('/api/erp/ledger/vouchers', [])->assertForbidden();
    }

    public function test_ledger_permissions_separate_viewing_drafting_and_posting(): void
    {
        $teacher = User::where('email', 'teacher@lms.test')->first();

        $this->actingAs($teacher, 'sanctum')->getJson('/api/erp/ledger/vouchers')->assertForbidden();
        $this->actingAs($teacher, 'sanctum')->getJson('/api/erp/ledger/trial-balance')->assertForbidden();
        $this->actingAs($teacher, 'sanctum')->postJson('/api/erp/ledger/accounts', ['name' => 'X'])->assertForbidden();

        $this->actingAs($this->accountant, 'sanctum')
            ->postJson('/api/erp/ledger/vouchers', $this->voucher([['1100', 50000, 0], ['3100', 0, 50000]]))
            ->assertCreated();
    }

    public function test_draft_voucher_gets_a_number_and_balances(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/ledger/vouchers', $this->voucher([['1100', 150050, 0], ['3100', 0, 150050]]));

        $response->assertCreated()
            ->assertJsonPath('data.voucher_no', 'JV-2026-00001')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.total_debit', 150050)
            ->assertJsonPath('data.fiscal_year', 'FY 2026-27')
            ->assertJsonCount(2, 'data.lines');

        $this->assertDatabaseHas('activity_logs', ['module' => 'vouchers', 'action' => 'created']);
    }

    public function test_unbalanced_one_sided_group_and_single_line_vouchers_are_rejected(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/ledger/vouchers', $this->voucher([['1100', 10000, 0], ['3100', 0, 9000]]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.lines.0', 'Debits (Rs 100.00) and credits (Rs 90.00) must be equal.');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/ledger/vouchers', $this->voucher([['1100', 10000, 10000], ['3100', 0, 0]]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.first_key', 'lines.0.debit')
            ->assertJsonPath('errors.first_message', 'Enter either a debit or a credit amount on each line.');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/ledger/vouchers', $this->voucher([['1200', 10000, 0], ['3100', 0, 10000]]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.first_key', 'lines.0.account_id')
            ->assertJsonPath('errors.first_message', 'Choose an active account that is not a group heading.');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/ledger/vouchers', $this->voucher([['1100', 10000, 0]]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.lines.0', 'A voucher needs at least two lines.');

        $this->assertSame(0, JournalEntry::count());
        $this->assertSame(1, NumberSequence::where('type', 'voucher_jv')->value('next_number'));
    }

    public function test_vouchers_need_an_open_fiscal_year(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/ledger/vouchers', $this->voucher([['1100', 100, 0], ['3100', 0, 100]], '2030-01-01'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.entry_date.0', 'No fiscal year covers this date. Add it under Fiscal Years first.');

        $draft = app(JournalService::class)->createDraft($this->voucher([['1100', 100, 0], ['3100', 0, 100]]));
        FiscalYear::where('name', 'FY 2026-27')->update(['status' => FiscalYear::STATUS_CLOSED]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/ledger/vouchers/'.$draft->id.'/post')
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.entry_date.0', 'FY 2026-27 is closed. Reopen it or use a date in an open year.');
    }

    public function test_draft_can_be_edited_and_deleted_but_posted_voucher_is_locked(): void
    {
        $draft = app(JournalService::class)->createDraft($this->voucher([['1100', 100, 0], ['3100', 0, 100]]));

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/erp/ledger/vouchers/'.$draft->id, ['entry_date' => '2026-10-21', 'narration' => 'Fixed', 'lines' => $this->voucher([['1110', 300, 0], ['3100', 0, 300]])['lines']])
            ->assertOk()
            ->assertJsonPath('data.total_debit', 300)
            ->assertJsonPath('data.lines.0.account', '1110 Petty Cash');

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/ledger/vouchers/'.$draft->id.'/post')
            ->assertOk()
            ->assertJsonPath('data.status', 'posted')
            ->assertJsonPath('data.posted_by', 'Super Administrator');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/erp/ledger/vouchers/'.$draft->id, ['entry_date' => '2026-10-21', 'lines' => $this->voucher([['1110', 5, 0], ['3100', 0, 5]])['lines']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.voucher.0', 'A posted voucher cannot be edited. Reverse it instead.');

        $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/erp/ledger/vouchers/'.$draft->id)->assertUnprocessable();
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/ledger/vouchers/'.$draft->id.'/post')->assertUnprocessable();

        $this->assertDatabaseHas('activity_logs', ['module' => 'vouchers', 'action' => 'posted', 'subject_id' => $draft->id]);

        $other = app(JournalService::class)->createDraft($this->voucher([['1100', 100, 0], ['3100', 0, 100]]));
        $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/erp/ledger/vouchers/'.$other->id)->assertOk();
        $this->assertDatabaseMissing('journal_lines', ['journal_entry_id' => $other->id]);
    }

    public function test_reversal_creates_an_opposite_posted_voucher_and_zeroes_the_balance(): void
    {
        $posted = app(JournalService::class)->record('JV', '2026-10-20', 'Capital introduced', [
            ['account_id' => $this->account('1100')->id, 'debit' => 500000],
            ['account_id' => $this->account('3100')->id, 'credit' => 500000],
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/ledger/vouchers/'.$posted->id.'/reverse', ['reason' => 'Entered twice'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'posted')
            ->assertJsonPath('data.reverses.voucher_no', $posted->voucher_no)
            ->assertJsonPath('data.lines.0.credit', 500000);

        $this->assertSame(JournalEntry::STATUS_REVERSED, $posted->refresh()->status);
        $this->assertSame($response->json('data.id'), $posted->reversed_by_entry_id);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/ledger/vouchers/'.$posted->id.'/reverse', ['reason' => 'Again'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.voucher.0', 'This voucher has already been reversed.');

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/erp/ledger/accounts/'.$this->account('1100')->id)
            ->assertJsonPath('data.balance', 0);
    }

    public function test_trial_balance_and_ledger_include_opening_balances_and_posted_entries_only(): void
    {
        $this->account('1100')->update(['opening_balance' => 100000, 'opening_balance_type' => 'debit']);
        $this->account('3100')->update(['opening_balance' => 100000, 'opening_balance_type' => 'credit']);

        $journal = app(JournalService::class);
        $journal->record('RV', '2026-09-01', 'Fees', [
            ['account_id' => $this->account('1100')->id, 'debit' => 250000],
            ['account_id' => $this->account('4100')->id, 'credit' => 250000],
        ]);
        $journal->record('PV', '2026-10-05', 'Electricity', [
            ['account_id' => $this->account('5200')->id, 'debit' => 40000],
            ['account_id' => $this->account('1100')->id, 'credit' => 40000],
        ]);
        $journal->createDraft($this->voucher([['1100', 999900, 0], ['4900', 0, 999900]]));

        $trial = $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/ledger/trial-balance?as_of=2026-10-31')->assertOk();

        $rows = collect($trial->json('data.rows'))->keyBy('code');
        $this->assertSame(310000, $rows['1100']['debit']);
        $this->assertSame(250000, $rows['4100']['credit']);
        $this->assertSame(40000, $rows['5200']['debit']);
        $this->assertTrue($trial->json('data.is_balanced'));
        $this->assertSame(350000, $trial->json('data.total_debit'));

        $ledger = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/erp/ledger/accounts/'.$this->account('1100')->id.'/ledger?draw=1&from=2026-10-01&to=2026-10-31')
            ->assertOk();

        $this->assertSame(350000, $ledger->json('data.summary.opening_balance'));
        $this->assertSame(40000, $ledger->json('data.summary.total_credit'));
        $this->assertSame(310000, $ledger->json('data.summary.closing_balance'));
        $this->assertSame(1, $ledger->json('data.recordsTotal'));
    }

    public function test_account_tree_rules(): void
    {
        $assetType = $this->account('1000')->account_type_id;
        $incomeGroup = $this->account('4000');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/ledger/accounts', ['account_type_id' => $assetType, 'parent_id' => $incomeGroup->id, 'code' => '1120', 'name' => 'Cash at Gate'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.parent_id.0', 'The parent belongs to a different account type.');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/ledger/accounts', ['account_type_id' => $assetType, 'parent_id' => $this->account('1100')->id, 'code' => '1120', 'name' => 'Cash at Gate'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.parent_id.0', 'The parent must be a group account.');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/ledger/accounts', ['account_type_id' => $assetType, 'code' => '1100', 'name' => 'Duplicate'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.code.0', 'Another account already uses this code.');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/ledger/accounts', ['account_type_id' => $assetType, 'code' => '1120', 'name' => 'Cash at Gate', 'opening_balance' => 5000])
            ->assertUnprocessable()
            ->assertJsonPath('errors.first_key', 'opening_balance_type');

        $created = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/ledger/accounts', ['account_type_id' => $assetType, 'parent_id' => $this->account('1000')->id, 'code' => '1120', 'name' => 'Cash at Gate', 'is_cash_account' => true])
            ->assertCreated()
            ->assertJsonPath('data.balance', 0)
            ->assertJsonPath('data.is_system', false);

        $group = $this->account('1600');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/erp/ledger/accounts/'.$group->id, ['account_type_id' => $assetType, 'parent_id' => $this->account('1610')->id, 'code' => '1600', 'name' => 'Fixed Assets', 'is_group' => true])
            ->assertUnprocessable();

        $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/erp/ledger/accounts/'.$this->account('1300')->id)
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.account.0', 'Built-in accounts cannot be deleted.');

        $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/erp/ledger/accounts/'.$this->account('1210')->id)
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.account.0', 'A payment method, bank account or vendor still uses this account.');

        $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/erp/ledger/accounts/'.$created->json('data.id'))->assertOk();
    }

    public function test_account_with_entries_cannot_become_a_group_or_be_deleted(): void
    {
        app(JournalService::class)->record('JV', '2026-10-20', 'Supplies', [
            ['account_id' => $this->account('5600')->id, 'debit' => 1000],
            ['account_id' => $this->account('1100')->id, 'credit' => 1000],
        ]);

        $stationery = $this->account('5600');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/erp/ledger/accounts/'.$stationery->id, ['account_type_id' => $stationery->account_type_id, 'parent_id' => $stationery->parent_id, 'code' => '5600', 'name' => 'Stationery', 'is_group' => true])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.is_group.0', 'This account already has entries, so it cannot become a group heading.');

        $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/erp/ledger/accounts/'.$stationery->id)
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.account.0', 'This account has journal entries. Mark it inactive instead.');
    }

    public function test_fiscal_years_cannot_overlap_and_close_is_logged(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/ledger/fiscal-years', ['name' => 'FY X', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.starts_on.0', 'These dates overlap FY 2026-27.');

        $year = FiscalYear::where('name', 'FY 2026-27')->first();

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/ledger/fiscal-years/'.$year->id.'/close')
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/ledger/fiscal-years/'.$year->id.'/close')->assertUnprocessable();
        $this->assertDatabaseHas('activity_logs', ['module' => 'fiscal_years', 'action' => 'closed', 'subject_id' => $year->id]);

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/ledger/fiscal-years/'.$year->id.'/reopen')
            ->assertOk()
            ->assertJsonPath('data.status', 'open');
    }

    public function test_new_voucher_type_gets_its_own_number_series_and_code_is_fixed(): void
    {
        $created = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/ledger/voucher-types', ['name' => 'Bank Receipt', 'code' => 'brv'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'BRV')
            ->assertJsonPath('data.number_sequence_type', 'voucher_brv');

        $this->assertSame('BRV-', NumberSequence::where('type', 'voucher_brv')->value('prefix'));

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/erp/ledger/voucher-types/'.$created->json('data.id'), ['name' => 'Bank Receipts', 'code' => 'XYZ'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.code.0', 'The code of an existing voucher type cannot change.');

        $journalVoucher = VoucherType::where('code', 'JV')->first();
        $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/erp/ledger/voucher-types/'.$journalVoucher->id)
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.voucher_type.0', 'Built-in voucher types cannot be deleted.');
    }

    public function test_payment_methods_bank_accounts_and_vendors(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/ledger/payment-methods', ['name' => 'Bank Group', 'code' => 'grp', 'account_id' => $this->account('1200')->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.account_id.0', 'Choose an active cash or bank account (not a group heading).');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/ledger/payment-methods', ['name' => 'Raast', 'code' => 'raast', 'account_id' => $this->account('1210')->id, 'is_online' => true, 'charges_percent' => 1.5])
            ->assertCreated()
            ->assertJsonPath('data.code', 'RAAST')
            ->assertJsonPath('data.charges_percent', '1.50');

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/payment-methods')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'CASH');

        $hbl = Lookup::where('type', 'bank')->where('name', 'HBL')->value('id');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/ledger/bank-accounts', ['account_id' => $this->account('1210')->id, 'bank_id' => $hbl, 'account_title' => 'Second', 'account_number' => '1'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.account_id.0', 'This ledger account is already linked to another bank account.');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/ledger/vendors', ['name' => 'City Stationers', 'payable_account_id' => $this->account('2100')->id])
            ->assertCreated()
            ->assertJsonPath('data.payable_account_label', '2100 Accounts Payable');
    }
}

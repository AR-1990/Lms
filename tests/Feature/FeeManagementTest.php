<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Account;
use App\Models\ConcessionType;
use App\Models\FeeHead;
use App\Models\FeeInvoice;
use App\Models\FeeStructure;
use App\Models\JournalEntry;
use App\Models\PaymentMethod;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\AccountService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class FeeManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AcademicSession $session;

    private Student $john;

    /**
     * Seeded fee heads, structures and demo students (John Doe is in Grade 5 A).
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(10, 0));
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->session = AcademicSession::where('is_current', true)->first();
        $this->john = Student::where('first_name', 'John')->first();
    }

    private function generate(string $month = '2026-10'): TestResponse
    {
        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/fees/invoices/generate', [
            'academic_session_id' => $this->session->id,
            'billing_month' => $month,
        ]);
    }

    private function balanceOf(string $code): int
    {
        $account = Account::where('code', $code)->first();

        return app(AccountService::class)->balanceOf($account);
    }

    private function pay(int $amount, array $extra = []): TestResponse
    {
        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/fees/payments', [
            'student_id' => $this->john->id,
            'payment_method_id' => PaymentMethod::where('code', 'CASH')->value('id'),
            'amount' => $amount,
            'received_on' => '2026-10-22',
            ...$extra,
        ]);
    }

    public function test_monthly_generation_bills_each_student_once_and_posts_to_the_ledger(): void
    {
        $this->generate()->assertCreated()->assertJsonPath('data.generated_count', 5);

        $invoice = FeeInvoice::where('student_id', $this->john->id)->firstOrFail();
        $this->assertSame('monthly', $invoice->invoice_type);
        $this->assertSame(800000, $invoice->total);
        $this->assertSame('2026-10-22', $invoice->due_date->toDateString(), 'A due day already past this month falls due on the issue date.');
        $this->assertSame(JournalEntry::STATUS_POSTED, $invoice->journalEntry->status);

        $this->generate()->assertCreated()->assertJsonPath('data.generated_count', 0)->assertJsonPath('data.skipped_count', 5);

        $this->generate('2026-11');
        $this->assertSame('2026-11-10', FeeInvoice::where('student_id', $this->john->id)->whereDate('billing_month', '2026-11-01')->first()->due_date->toDateString());

        $receivable = (int) FeeInvoice::sum('total');
        $this->assertSame($receivable, $this->balanceOf('1300'));
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/ledger/trial-balance?as_of=2026-10-31')->assertJsonPath('data.is_balanced', true);
    }

    public function test_approved_concession_discounts_the_challan_and_posts_concession_expense(): void
    {
        $sibling = ConcessionType::where('name', 'Sibling')->first();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/fees/concessions', ['student_id' => $this->john->id, 'concession_type_id' => $sibling->id, 'valid_from' => '2026-08-01'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.value', 1000);

        $this->generate();
        $invoice = FeeInvoice::where('student_id', $this->john->id)->with('items')->firstOrFail();

        $this->assertSame(80000, $invoice->concession_total);
        $this->assertSame(720000, $invoice->total);
        $this->assertSame(80000, $this->balanceOf('5400'));
    }

    public function test_concession_needing_approval_does_not_apply_until_approved(): void
    {
        $merit = ConcessionType::where('name', 'Merit Scholarship')->first();

        $concession = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/fees/concessions', ['student_id' => $this->john->id, 'concession_type_id' => $merit->id, 'valid_from' => '2026-08-01'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->json('data.id');

        $this->generate('2026-10');
        $this->assertSame(0, FeeInvoice::where('student_id', $this->john->id)->value('concession_total'));

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/fees/concessions/{$concession}/decision", ['decision' => 'approved'])->assertOk();
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/fees/concessions/{$concession}/decision", ['decision' => 'rejected'])->assertUnprocessable();

        $this->generate('2026-11');
        $november = FeeInvoice::where('student_id', $this->john->id)->whereDate('billing_month', '2026-11-01')->first();
        $this->assertSame(200000, $november->concession_total);
    }

    public function test_payments_settle_oldest_challans_first_and_keep_the_rest_as_advance(): void
    {
        $this->generate('2026-10');
        $this->generate('2026-11');
        [$october, $november] = FeeInvoice::where('student_id', $this->john->id)->orderBy('billing_month')->get()->all();

        $this->pay(500000)->assertCreated()->assertJsonPath('data.allocations.0.invoice_no', $october->invoice_no);
        $this->assertSame('partially_paid', $october->refresh()->status);
        $this->assertSame(300000, $october->balance);

        $receipt = $this->pay(1200000)->assertCreated()
            ->assertJsonPath('data.advance_amount', 100000)
            ->assertJsonCount(2, 'data.allocations');

        $this->assertSame('paid', $october->refresh()->status);
        $this->assertSame('paid', $november->refresh()->status);
        $this->assertSame(-100000, $this->balanceOf('2300'));
        $this->assertSame(1700000, $this->balanceOf('1100'));

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/fees/payments/'.$receipt->json('data.id').'/reverse', ['reason' => 'Counterfeit note'])
            ->assertOk()
            ->assertJsonPath('data.status', 'reversed');

        $this->assertSame(300000, $october->refresh()->balance);
        $this->assertSame('posted', $november->refresh()->status);
        $this->assertSame(0, $this->balanceOf('2300'));
        $this->assertSame(500000, $this->balanceOf('1100'));
    }

    public function test_payment_method_rules_are_enforced(): void
    {
        $this->generate();

        $this->pay(1000, ['payment_method_id' => PaymentMethod::where('code', 'JAZZ')->value('id')])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.reference_no.0', 'JazzCash needs a transaction or cheque number.');

        $this->pay(1000, ['payment_method_id' => PaymentMethod::where('code', 'CHQ')->value('id'), 'reference_no' => '000123'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.first_key', 'cheque_bank_id');

        $this->pay(1000, ['received_on' => '2026-12-01'])->assertUnprocessable()->assertJsonPath('errors.first_key', 'received_on');
    }

    public function test_waive_and_cancel_rules(): void
    {
        $this->generate();
        $invoice = FeeInvoice::where('student_id', $this->john->id)->first();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/erp/fees/invoices/{$invoice->id}/waive", ['amount' => 900000, 'reason' => 'Too much'])
            ->assertUnprocessable();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/erp/fees/invoices/{$invoice->id}/waive", ['amount' => 100000, 'reason' => 'Flood relief'])
            ->assertOk()
            ->assertJsonPath('data.balance', 700000)
            ->assertJsonPath('data.status', 'partially_paid');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/erp/fees/invoices/{$invoice->id}/cancel", ['reason' => 'Mistake'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.reason.0', 'The narration field must be at least 10 characters.');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/erp/fees/invoices/{$invoice->id}/cancel", ['reason' => 'Mistake in billing'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.invoice.0', 'Only an unpaid challan can be cancelled. Reverse its receipts first.');

        $other = FeeInvoice::where('student_id', '!=', $this->john->id)->first();
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/erp/fees/invoices/{$other->id}/cancel", ['reason' => 'Left school'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancelled_by_name', 'Super Administrator');

        $this->assertSame(JournalEntry::STATUS_REVERSED, $other->refresh()->journalEntry->status);
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/ledger/trial-balance?as_of=2026-10-31')->assertJsonPath('data.is_balanced', true);
    }

    public function test_manual_challan_uses_chosen_heads(): void
    {
        $exam = FeeHead::where('code', 'EXM')->first();

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/fees/invoices', [
            'student_id' => $this->john->id,
            'issue_date' => '2026-10-22',
            'due_date' => '2026-10-30',
            'items' => [['fee_head_id' => $exam->id, 'amount' => 150000, 'description' => 'Mid-term exam']],
        ])->assertCreated()
            ->assertJsonPath('data.invoice_type', 'other')
            ->assertJsonPath('data.total', 150000)
            ->assertJsonPath('data.items.0.description', 'Mid-term exam');
    }

    public function test_parents_see_only_their_own_childrens_challans(): void
    {
        $this->generate();
        $parent = User::where('email', 'parent@lms.test')->first();
        $johnInvoice = FeeInvoice::where('student_id', $this->john->id)->first();
        $otherInvoice = FeeInvoice::where('student_id', '!=', $this->john->id)->first();

        $this->actingAs($parent, 'sanctum')
            ->getJson('/api/erp/fees/invoices')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.invoice_no', $johnInvoice->invoice_no);

        $this->actingAs($parent, 'sanctum')->getJson('/api/erp/fees/invoices?student_id='.$otherInvoice->student_id)->assertJsonPath('data.meta.total', 0);
        $this->actingAs($parent, 'sanctum')->getJson('/api/erp/fees/invoices/'.$otherInvoice->id)->assertNotFound();
        $this->actingAs($parent, 'sanctum')->getJson('/api/erp/fees/invoices/'.$johnInvoice->id)->assertOk();
        $this->actingAs($parent, 'sanctum')->postJson('/api/erp/fees/payments', [])->assertForbidden();
    }

    public function test_fee_structure_rules_and_copy(): void
    {
        $grade5 = SchoolClass::where('name', 'Grade 5')->first();
        $tuition = FeeHead::where('code', 'TUI')->first();

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/fees/structures', [
            'academic_session_id' => $this->session->id,
            'class_id' => $grade5->id,
            'name' => 'Duplicate',
            'items' => [['fee_head_id' => $tuition->id, 'charge_on' => 'recurring', 'amount' => 100]],
        ])->assertUnprocessable()->assertJsonPath('errors.first_key', 'class_id');

        $structure = FeeStructure::where('class_id', $grade5->id)->first();
        $this->actingAs($this->admin, 'sanctum')->putJson("/api/erp/fees/structures/{$structure->id}", [
            'academic_session_id' => $this->session->id,
            'class_id' => $grade5->id,
            'name' => $structure->name,
            'items' => [['fee_head_id' => $tuition->id, 'charge_on' => 'recurring', 'amount' => 900000, 'due_day' => 5, 'months' => [10, 11]]],
        ])->assertOk()->assertJsonPath('data.items.0.months', [10, 11]);

        $this->assertDatabaseHas('activity_logs', ['module' => 'fee_structures', 'action' => 'items_changed', 'subject_id' => $structure->id]);

        $this->generate('2026-12');
        $this->assertFalse(FeeInvoice::where('student_id', $this->john->id)->exists());

        $next = AcademicSession::factory()->create(['name' => '2027-28', 'starts_on' => '2027-08-01', 'ends_on' => '2028-07-31']);
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/fees/structures/copy', ['from_session_id' => $this->session->id, 'to_session_id' => $next->id])
            ->assertOk()
            ->assertJsonPath('data.copied', 13);

        $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/erp/fees/heads/'.$tuition->id)->assertUnprocessable();
    }

    public function test_fee_permissions(): void
    {
        $teacher = User::where('email', 'teacher@lms.test')->first();
        $accountant = User::where('email', 'accounts@lms.test')->first();

        $this->actingAs($teacher, 'sanctum')->postJson('/api/erp/fees/invoices/generate', [])->assertForbidden();
        $this->actingAs($teacher, 'sanctum')->getJson('/api/erp/fees/heads/datatable')->assertForbidden();
        $this->actingAs($accountant, 'sanctum')->getJson('/api/erp/fees/heads/datatable?draw=1')->assertOk();
        $this->actingAs($accountant, 'sanctum')->postJson('/api/erp/fees/invoices/generate', ['academic_session_id' => $this->session->id, 'billing_month' => '2026-10'])->assertCreated();
    }

    public function test_fee_screens_render_for_accounts_staff_and_are_forbidden_to_teachers(): void
    {
        $teacher = User::where('email', 'teacher@lms.test')->first();
        $accountant = User::where('email', 'accounts@lms.test')->first();

        foreach (['challans', 'receipts', 'structures', 'heads', 'concessions', 'concession-types', 'late-fees'] as $screen) {
            $this->actingAs($accountant)
                ->get(route('accounts.fees.'.$screen))
                ->assertOk()
                ->assertSee('data-fees="'.$screen.'"', false)
                ->assertSee('js/admin-fees.js', false);

            $this->actingAs($teacher)->get(route('accounts.fees.'.$screen))->assertForbidden();
        }
    }

    public function test_fee_staff_can_look_up_students_without_managing_student_records(): void
    {
        $accountant = User::where('email', 'accounts@lms.test')->first();
        $teacher = User::where('email', 'teacher@lms.test')->first();

        $this->actingAs($accountant, 'sanctum')->getJson('/api/erp/students')->assertForbidden();
        $this->actingAs($accountant, 'sanctum')
            ->getJson('/api/erp/fees/students?search=John')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.admission_no', $this->john->admission_no)
            ->assertJsonPath('data.items.0.class_name', 'Grade 5');

        $this->actingAs($teacher, 'sanctum')->getJson('/api/erp/fees/students?search=John')->assertForbidden();
    }

    public function test_challan_grid_search_matches_the_student_name(): void
    {
        $this->generate()->assertCreated();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/erp/fees/invoices/datatable?draw=1&columns[0][data]=payer_name&columns[0][name]=payer_name&columns[0][searchable]=true&search[value]='.urlencode($this->john->fullName()))
            ->assertOk()
            ->assertJsonPath('data.recordsFiltered', 1)
            ->assertJsonPath('data.data.0.student_id', $this->john->id);
    }
}

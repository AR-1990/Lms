<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Account;
use App\Models\FeeHead;
use App\Models\FeeInvoice;
use App\Models\FeeInvoiceItem;
use App\Models\FeePayment;
use App\Models\FeeRefund;
use App\Models\JournalEntry;
use App\Models\Lookup;
use App\Models\PaymentMethod;
use App\Models\Student;
use App\Models\User;
use App\Services\FeeInvoiceService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StudentLeavingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Student $john;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-22 10:00'));
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->john = Student::where('first_name', 'John')->first();
        $this->actingAs($this->admin, 'sanctum');
    }

    private function cash(): int
    {
        return PaymentMethod::where('code', 'CASH')->value('id');
    }

    private function pay(int $amount): int
    {
        return $this->postJson('/api/erp/fees/payments', ['student_id' => $this->john->id, 'payment_method_id' => $this->cash(), 'amount' => $amount, 'received_on' => today()->toDateString()])
            ->assertCreated()
            ->json('data.id');
    }

    private function withdraw(): void
    {
        $this->postJson("/api/erp/students/{$this->john->id}/status", ['status' => 'withdrawn', 'effective_on' => '2026-10-21', 'reason_id' => Lookup::where('type', 'withdrawal_reason')->value('id')])->assertOk();
    }

    /**
     * A paid admission challan holding a Rs 5,000 security deposit.
     */
    private function paidDeposit(): void
    {
        $invoice = FeeInvoice::create([
            'invoice_no' => 'INV-DEP-1', 'student_id' => $this->john->id, 'academic_session_id' => AcademicSession::where('is_current', true)->value('id'),
            'invoice_type' => 'admission', 'issue_date' => '2026-08-01', 'due_date' => '2026-08-10',
            'subtotal' => 500000, 'total' => 500000, 'paid_total' => 500000, 'balance' => 0, 'status' => 'paid',
        ]);
        FeeInvoiceItem::create(['fee_invoice_id' => $invoice->id, 'fee_head_id' => FeeHead::where('code', 'SEC')->value('id'), 'description' => 'Security Deposit', 'amount' => 500000, 'net_amount' => 500000]);
    }

    /**
     * @return array<string, mixed>
     */
    private function refundPayload(array $overrides = []): array
    {
        return ['student_id' => $this->john->id, 'source' => 'deposit', 'amount' => 300000, 'payment_method_id' => $this->cash(), 'refunded_on' => '2026-10-22', 'reason' => 'Left the school', ...$overrides];
    }

    public function test_certificate_waits_for_leaving_status_and_cleared_dues(): void
    {
        $certificate = ['issued_on' => '2026-10-22', 'conduct_id' => Lookup::where('type', 'conduct')->where('name', 'Good')->value('id')];
        $this->postJson('/api/erp/fees/invoices/generate', ['academic_session_id' => AcademicSession::where('is_current', true)->value('id'), 'billing_month' => '2026-10'])->assertCreated();
        $due = FeeInvoice::where('student_id', $this->john->id)->value('balance');

        $this->postJson("/api/erp/students/{$this->john->id}/leaving-certificate", $certificate)
            ->assertUnprocessable()
            ->assertJsonPath('errors.first_key', 'slc');

        $this->withdraw();

        $this->getJson("/api/erp/students/{$this->john->id}/clearance")
            ->assertOk()
            ->assertJsonPath('data.is_leaving', true)
            ->assertJsonPath('data.is_clear', false)
            ->assertJsonPath('data.fee_due', $due);

        $this->postJson("/api/erp/students/{$this->john->id}/leaving-certificate", $certificate)
            ->assertUnprocessable()
            ->assertJsonFragment(['Clearance is not complete: Rs '.number_format($due / 100).' of fees is still due.']);

        $this->pay($due);

        $slcNo = $this->postJson("/api/erp/students/{$this->john->id}/leaving-certificate", $certificate)
            ->assertOk()
            ->assertJsonPath('data.is_clear', true)
            ->json('data.slc.no');
        $this->assertStringStartsWith('SLC-', $slcNo);

        $this->postJson("/api/erp/students/{$this->john->id}/leaving-certificate", [...$certificate, 'remarks' => 'Hard-working student.'])
            ->assertOk()
            ->assertJsonPath('data.slc.no', $slcNo)
            ->assertJsonPath('data.slc.remarks', 'Hard-working student.');

        $this->get("/api/erp/students/{$this->john->id}/leaving-certificate/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertDatabaseHas('activity_logs', ['module' => 'students', 'action' => 'printed']);
    }

    public function test_deposit_refund_is_limited_to_what_is_held_and_posts_a_payment_voucher(): void
    {
        $this->paidDeposit();

        $this->getJson("/api/erp/students/{$this->john->id}/clearance")->assertJsonPath('data.deposit_available', 500000);

        $this->postJson('/api/erp/fees/refunds', $this->refundPayload(['amount' => 600000]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.first_key', 'amount');

        $refundId = $this->postJson('/api/erp/fees/refunds', $this->refundPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'posted')
            ->json('data.id');

        $entry = JournalEntry::with('lines')->find(FeeRefund::find($refundId)->journal_entry_id);
        $this->assertSame(JournalEntry::STATUS_POSTED, $entry->status);
        $this->assertEquals(300000, $entry->lines->firstWhere('account_id', Account::where('code', '2500')->value('id'))->debit);
        $this->assertEquals(300000, $entry->lines->firstWhere('account_id', PaymentMethod::find($this->cash())->account_id)->credit);

        $this->getJson("/api/erp/students/{$this->john->id}/clearance")
            ->assertJsonPath('data.deposit_available', 200000)
            ->assertJsonPath('data.refunds.0.status', 'posted');

        $this->postJson("/api/erp/fees/refunds/{$refundId}/reverse", ['reason' => 'Paid by mistake'])->assertOk()->assertJsonPath('data.status', 'reversed');
        $this->postJson("/api/erp/fees/refunds/{$refundId}/reverse", ['reason' => 'Again'])->assertUnprocessable();

        $this->assertSame(JournalEntry::STATUS_REVERSED, $entry->fresh()->status);
        $this->getJson("/api/erp/students/{$this->john->id}/clearance")->assertJsonPath('data.deposit_available', 500000);

        $this->getJson('/api/erp/fees/refunds/datatable?draw=1&start=0&length=10')
            ->assertOk()
            ->assertJsonPath('data.recordsTotal', 1)
            ->assertJsonPath('data.data.0.student_name', $this->john->fullName());
    }

    public function test_refunded_advance_is_not_used_on_later_challans_and_blocks_receipt_reversal(): void
    {
        $receipt = $this->pay(500000);
        $this->assertSame(500000, app(FeeInvoiceService::class)->advanceBalance($this->john->id));

        $this->postJson('/api/erp/fees/refunds', $this->refundPayload(['source' => 'advance', 'amount' => 300000]))->assertCreated();
        $this->assertSame(200000, app(FeeInvoiceService::class)->advanceBalance($this->john->id));

        $this->postJson('/api/erp/fees/invoices/generate', ['academic_session_id' => AcademicSession::where('is_current', true)->value('id'), 'billing_month' => '2026-10'])->assertCreated();
        $this->assertSame(200000, FeeInvoice::where('student_id', $this->john->id)->value('paid_total'));
        $this->assertSame(0, app(FeeInvoiceService::class)->advanceBalance($this->john->id));

        $this->postJson("/api/erp/fees/payments/{$receipt}/reverse", ['reason' => 'Wrong student'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.first_key', 'payment');
    }

    public function test_deposit_pays_the_dues_and_the_counter_cannot_use_the_deposit_method(): void
    {
        $this->paidDeposit();
        $this->postJson('/api/erp/fees/invoices/generate', ['academic_session_id' => AcademicSession::where('is_current', true)->value('id'), 'billing_month' => '2026-10'])->assertCreated();
        $due = (int) FeeInvoice::where('student_id', $this->john->id)->where('invoice_type', 'monthly')->value('balance');
        $adjusted = min(500000, $due);
        $depositMethod = PaymentMethod::where('code', PaymentMethod::DEPOSIT_ADJUSTMENT)->first();

        $this->postJson('/api/erp/fees/payments', ['student_id' => $this->john->id, 'payment_method_id' => $depositMethod->id, 'amount' => 1000, 'received_on' => '2026-10-22'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.first_key', 'payment_method_id');

        $this->postJson("/api/erp/fees/students/{$this->john->id}/adjust-deposit")
            ->assertOk()
            ->assertJsonPath('data.fee_due', $due - $adjusted)
            ->assertJsonPath('data.deposit_available', 500000 - $adjusted);

        $receipt = FeePayment::where('payment_method_id', $depositMethod->id)->firstOrFail();
        $this->assertSame($adjusted, $receipt->amount);
        $this->assertEquals($adjusted, JournalEntry::with('lines')->find($receipt->journal_entry_id)->lines->firstWhere('account_id', Account::where('code', '2500')->value('id'))->debit);

        $this->postJson("/api/erp/fees/students/{$this->john->id}/adjust-deposit")->assertUnprocessable()->assertJsonPath('errors.first_key', 'deposit');

        $this->postJson("/api/erp/fees/payments/{$receipt->id}/reverse", ['reason' => 'Parents will pay in cash'])->assertOk();
        $this->getJson("/api/erp/students/{$this->john->id}/clearance")->assertJsonPath('data.deposit_available', 500000)->assertJsonPath('data.fee_due', $due);
    }

    public function test_refunds_need_the_refund_permission(): void
    {
        $this->paidDeposit();
        $teacher = User::where('email', 'teacher@lms.test')->first();

        $this->actingAs($this->admin)->get("/admin/students/{$this->john->id}")->assertOk()->assertSee('Leaving &amp; clearance', false)->assertSee('Pay back to the family');
        $this->actingAs($this->admin)->get('/accounts/fees/refunds')->assertOk()->assertSee('Refund register');
        $this->actingAs($teacher)->get('/accounts/fees/refunds')->assertForbidden();

        $this->actingAs($teacher, 'sanctum')->postJson('/api/erp/fees/refunds', $this->refundPayload())->assertForbidden()->assertJsonPath('success', false);
        $this->actingAs($teacher, 'sanctum')->getJson("/api/erp/students/{$this->john->id}/clearance")->assertForbidden();
        $this->assertSame(0, FeeRefund::count());
    }
}

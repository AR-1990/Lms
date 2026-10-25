<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Account;
use App\Models\FeeHead;
use App\Models\FeeInvoice;
use App\Models\FeeInvoiceBatch;
use App\Models\FeePayment;
use App\Models\LateFeeRule;
use App\Models\PaymentMethod;
use App\Models\Student;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\AccountService;
use App\Services\FeeAutomationService;
use App\Services\FeeInvoiceService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FeeAutomationTest extends TestCase
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

    private function generate(string $month): void
    {
        $this->postJson('/api/erp/fees/invoices/generate', ['academic_session_id' => AcademicSession::where('is_current', true)->value('id'), 'billing_month' => $month])->assertCreated();
    }

    private function challan(string $month): FeeInvoice
    {
        return FeeInvoice::where('student_id', $this->john->id)->whereDate('billing_month', $month.'-01')->firstOrFail();
    }

    private function pay(int $amount): int
    {
        return $this->postJson('/api/erp/fees/payments', ['student_id' => $this->john->id, 'payment_method_id' => PaymentMethod::where('code', 'CASH')->value('id'), 'amount' => $amount, 'received_on' => today()->toDateString()])
            ->assertCreated()
            ->json('data.id');
    }

    private function balanceOf(string $code): int
    {
        return app(AccountService::class)->balanceOf(Account::where('code', $code)->first());
    }

    private function rule(array $attributes): LateFeeRule
    {
        return LateFeeRule::create(['name' => 'Late fine', 'fee_head_id' => FeeHead::where('code', 'LPF')->value('id'), 'invoice_type' => 'monthly', 'is_active' => true, ...$attributes]);
    }

    public function test_an_advance_pays_the_next_challan_and_reversing_the_receipt_takes_it_back(): void
    {
        $this->generate('2026-10');
        $october = $this->challan('2026-10');
        $receipt = $this->pay($october->total + 300000);
        $this->assertSame(300000, app(FeeInvoiceService::class)->advanceBalance($this->john->id));

        $this->generate('2026-11');
        $november = $this->challan('2026-11');
        $this->assertSame(300000, $november->paid_total);
        $this->assertSame($november->total - 300000, $november->balance);
        $this->assertSame('partially_paid', $november->status);
        $this->assertSame(0, app(FeeInvoiceService::class)->advanceBalance($this->john->id));
        $this->assertSame(0, $this->balanceOf('2300'));
        $this->getJson("/api/erp/fees/invoices/{$november->id}")->assertOk()->assertJsonPath('data.payments.0.from_advance', true);

        $this->postJson("/api/erp/fees/payments/{$receipt}/reverse", ['reason' => 'Cheque bounced'])->assertOk();
        $this->assertSame($october->total, $october->refresh()->balance);
        $this->assertSame($november->total, $november->refresh()->balance);
        $this->assertSame(0, $this->balanceOf('2300'));
    }

    public function test_cancelling_a_challan_paid_only_from_advance_returns_the_advance(): void
    {
        $this->generate('2026-10');
        $this->pay($this->challan('2026-10')->total + 200000);
        $this->generate('2026-11');
        $november = $this->challan('2026-11');
        $this->assertSame(200000, $november->paid_total);

        $this->postJson("/api/erp/fees/invoices/{$november->id}/cancel", ['reason' => 'Student left in October'])->assertOk();

        $this->assertSame('cancelled', $november->refresh()->status);
        $this->assertSame(200000, app(FeeInvoiceService::class)->advanceBalance($this->john->id));
        $this->assertSame(-200000, $this->balanceOf('2300'));
    }

    public function test_a_fixed_late_fine_is_added_once_after_the_grace_days(): void
    {
        $this->generate('2026-10');
        $october = $this->challan('2026-10');
        $this->rule(['calculation' => 'fixed', 'amount' => 50000, 'grace_days' => 5]);
        $automation = app(FeeAutomationService::class);

        $this->assertSame(0, $automation->applyLateFees(Carbon::parse('2026-10-27'))['challans']);
        $this->assertGreaterThan(0, $automation->applyLateFees(Carbon::parse('2026-10-28'))['challans']);
        $automation->applyLateFees(Carbon::parse('2026-10-29'));

        $october->refresh();
        $this->assertSame(50000, $october->fine_total);
        $this->assertSame($october->subtotal - $october->concession_total + 50000, $october->total);
        $this->assertSame($october->total, $october->balance);
        $this->assertSame(1, $october->fines()->count());
        $this->assertSame(-50000 * FeeInvoice::where('fine_total', '>', 0)->count(), $this->balanceOf('4300'));
    }

    public function test_a_per_day_fine_grows_to_its_cap_and_a_paid_challan_is_left_alone(): void
    {
        $this->generate('2026-10');
        $october = $this->challan('2026-10');
        $this->rule(['calculation' => 'per_day', 'amount' => 10000, 'max_amount' => 50000, 'grace_days' => 0]);
        $automation = app(FeeAutomationService::class);

        $automation->applyLateFees(Carbon::parse('2026-10-25'));
        $this->assertSame(30000, $october->refresh()->fine_total);
        $automation->applyLateFees(Carbon::parse('2026-11-10'));
        $this->assertSame(50000, $october->refresh()->fine_total);

        $this->pay($october->balance);
        $this->assertSame('paid', $october->refresh()->status);
        $automation->applyLateFees(Carbon::parse('2026-11-20'));
        $this->assertSame(50000, $october->refresh()->fine_total);
    }

    public function test_monthly_challans_are_generated_on_the_chosen_day_once(): void
    {
        $this->putJson('/api/erp/fees/automation', ['fee_auto_generate_day' => 31])->assertOk()->assertJsonPath('data.fee_auto_generate_day', 31);
        $automation = app(FeeAutomationService::class);

        $this->assertNull($automation->autoGenerate(Carbon::parse('2026-11-29')));
        $this->actingAs($this->admin, 'web');
        $batch = $automation->autoGenerate(Carbon::parse('2026-11-30'));

        $this->assertInstanceOf(FeeInvoiceBatch::class, $batch);
        $this->assertSame('2026-11-01', $batch->billing_month->toDateString());
        $this->assertGreaterThan(0, $batch->generated_count);
        $this->assertNull($automation->autoGenerate(Carbon::parse('2026-11-30')));

        $this->putJson('/api/erp/fees/automation', ['fee_auto_generate_day' => null])->assertOk();
        $this->assertNull($automation->autoGenerate(Carbon::parse('2026-12-31')));
    }

    public function test_challan_pdfs_and_late_fee_rule_rules(): void
    {
        $this->generate('2026-10');
        $october = $this->challan('2026-10');
        $other = FeeInvoice::where('student_id', '!=', $this->john->id)->first();
        $batch = FeeInvoiceBatch::first();

        $this->get("/api/erp/fees/invoices/{$october->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get("/api/erp/fees/batches/{$batch->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');

        $parent = User::where('email', 'parent@lms.test')->first();
        $this->actingAs($parent)->get("/api/erp/fees/invoices/{$october->id}/pdf")->assertOk();
        $this->actingAs($parent)->get("/api/erp/fees/invoices/{$other->id}/pdf")->assertNotFound();
        $this->actingAs($parent)->get("/api/erp/fees/batches/{$batch->id}/pdf")->assertForbidden();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/fees/late-fee-rules', ['name' => 'Too much', 'calculation' => 'percent', 'amount' => 15000])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.amount.0', 'A percent fine cannot be more than 100%.');
        $this->postJson('/api/erp/fees/late-fee-rules', ['name' => '2% late charge', 'calculation' => 'percent', 'amount' => 200, 'grace_days' => 7])->assertCreated();
        $this->getJson('/api/erp/fees/late-fee-rules/datatable?draw=1')->assertOk()->assertJsonPath('data.recordsTotal', 2);
        $this->assertSame(0, FeePayment::count());
    }

    public function test_admins_and_accounts_are_alerted_about_challans_unpaid_after_the_due_date(): void
    {
        $this->generate('2026-10');
        $october = $this->challan('2026-10');
        $service = app(FeeAutomationService::class);
        $feeStaff = User::whereIn('email', ['admin@lms.test', 'accounts@lms.test'])->pluck('id');

        $this->assertSame(0, $service->overdueAlert($october->due_date->copy())['students']);
        $this->assertSame(0, UserNotification::where('event_key', 'fees_overdue')->count());

        $summary = $service->overdueAlert($october->due_date->copy()->addDay());

        $this->assertGreaterThan(0, $summary['students']);
        $this->assertSame($summary['students'], $summary['new']);
        $this->assertSame((int) FeeInvoice::whereIn('status', FeeInvoice::OPEN_STATUSES)->sum('balance'), $summary['amount']);
        $alerts = UserNotification::where('event_key', 'fees_overdue')->get();
        $this->assertEqualsCanonicalizing($feeStaff->all(), $alerts->pluck('user_id')->intersect($feeStaff)->values()->all());
        $this->assertFalse($alerts->contains('user_id', User::where('email', 'teacher@lms.test')->value('id')));
        $this->assertStringContainsString($this->john->fullName(), $alerts->first()->body);
        $this->assertStringContainsString('status=overdue', $alerts->first()->url);

        $this->travelTo($october->due_date->copy()->addDays(3));
        $this->getJson('/api/erp/fees/invoices/datatable?draw=1&status=overdue')->assertOk()->assertJsonPath('data.recordsFiltered', $summary['students']);
        $this->pay($october->balance);
        $this->assertSame($summary['students'] - 1, $service->overdueAlert(today())['students']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\PaymentMethod;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 24)->setTime(10, 0));
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        FeePayment::query()->update(['status' => 'reversed']);
        FeeInvoice::whereIn('status', FeeInvoice::OPEN_STATUSES)->update(['status' => 'paid', 'balance' => 0]);
        Expense::whereIn('status', ['approved', 'paid'])->update(['status' => 'cancelled']);
    }

    public function test_the_money_dashboard_adds_up_collections_dues_and_spending(): void
    {
        $student = Student::where('first_name', 'John')->where('last_name', 'Doe')->first();
        $cash = PaymentMethod::where('code', 'CASH')->value('id') ?? PaymentMethod::value('id');

        foreach ([['2026-10-24', 500000], ['2026-10-03', 300000], ['2026-09-10', 200000], ['2026-10-20', 999999, 'reversed']] as $index => $payment) {
            FeePayment::create(['receipt_no' => 'R-'.$index, 'student_id' => $student->id, 'payment_method_id' => $cash, 'amount' => $payment[1], 'received_on' => $payment[0], 'status' => $payment[2] ?? 'posted']);
        }

        FeeInvoice::create([
            'invoice_no' => 'T-1', 'student_id' => $student->id, 'enrollment_id' => $student->currentEnrollment->id, 'academic_session_id' => $student->currentEnrollment->academic_session_id,
            'invoice_type' => 'monthly', 'issue_date' => '2026-07-01', 'due_date' => '2026-08-01', 'subtotal' => 250000, 'total' => 250000, 'balance' => 250000, 'status' => 'posted',
        ]);
        Expense::factory()->create(['expense_date' => '2026-10-05', 'amount' => 120000, 'status' => 'paid']);
        Expense::factory()->create(['expense_date' => '2026-10-06', 'amount' => 80000, 'status' => 'approved']);
        Expense::factory()->create(['expense_date' => '2026-10-07', 'amount' => 70000, 'status' => 'draft']);

        $data = $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/admin/finance?months=6')->assertOk()->json('data');

        $this->assertSame(800000, $data['kpis']['collected_month']);
        $this->assertSame(500000, $data['kpis']['collected_today']);
        $this->assertSame(1, $data['kpis']['receipts_today']);
        $this->assertSame(250000, $data['kpis']['overdue']);
        $this->assertSame(1, $data['kpis']['overdue_students']);
        $this->assertSame(200000, $data['kpis']['expenses_month']);
        $this->assertSame(80000, $data['kpis']['awaiting_payment']);
        $this->assertCount(6, $data['trend']);
        $this->assertSame(['month' => '2026-09', 'collected' => 200000], array_intersect_key($data['trend'][4], array_flip(['month', 'collected'])));
        $this->assertSame(250000, $data['ageing'][2]['amount']);
        $this->assertCount(24, $data['daily']);
        $this->assertSame(500000, $data['daily'][23]['collected']);
        $this->assertSame('John Doe', $data['top_defaulters'][0]['student']);

        $this->actingAs(User::where('email', 'teacher@lms.test')->first(), 'sanctum')->getJson('/api/erp/admin/finance')->assertForbidden();
        $this->actingAs($this->admin)->get('/dashboard')->assertOk()->assertSee('js/admin-dashboard.js', false)->assertSee('Fee collection vs spending');
    }
}

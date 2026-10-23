<?php

namespace Tests\Feature;

use App\Models\FeeInvoice;
use App\Models\Student;
use App\Models\User;
use App\Services\ReportService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 24)->setTime(10, 0));
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
    }

    public function test_every_report_runs_with_its_columns_for_the_admin(): void
    {
        foreach (array_keys(ReportService::REPORTS) as $key) {
            $report = $this->actingAs($this->admin, 'sanctum')
                ->getJson("/api/erp/reports/{$key}?from=2026-01-01&to=2026-10-24&as_of=2026-10-24")
                ->assertOk()
                ->json('data');

            $this->assertNotEmpty($report['columns'], $key);
            $this->assertIsArray($report['rows'], $key);
        }
    }

    public function test_the_balance_sheet_balances_and_income_minus_expense_is_the_surplus(): void
    {
        $sheet = $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/reports/balance-sheet?as_of=2026-10-24')->assertOk()->json('data.summary');
        $this->assertSame(['Difference', 0, 'money'], $sheet[3]);

        $income = $this->getJson('/api/erp/reports/profit-loss?from=2026-01-01&to=2026-10-24')->json('data.summary');
        $this->assertSame(abs($income[0][1] - $income[1][1]), $income[2][1]);
    }

    public function test_defaulters_are_split_by_how_long_they_are_overdue(): void
    {
        $student = Student::where('first_name', 'John')->where('last_name', 'Doe')->first();
        FeeInvoice::whereIn('status', FeeInvoice::OPEN_STATUSES)->update(['status' => 'paid', 'balance' => 0]);

        foreach ([['2026-10-10', 100000], ['2026-08-01', 250000]] as $index => [$due, $balance]) {
            FeeInvoice::create([
                'invoice_no' => 'T-'.$index, 'student_id' => $student->id, 'enrollment_id' => $student->currentEnrollment->id, 'academic_session_id' => $student->currentEnrollment->academic_session_id, 'invoice_type' => 'monthly',
                'issue_date' => '2026-07-01', 'due_date' => $due, 'subtotal' => $balance, 'total' => $balance, 'balance' => $balance, 'status' => 'posted',
            ]);
        }

        $report = $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/reports/fee-defaulters?as_of=2026-10-24')->assertOk()->json('data');

        $this->assertSame(1, $report['meta']['total']);
        $this->assertSame(['d30' => 100000, 'd60' => 0, 'd90' => 250000, 'over90' => 0, 'balance' => 350000], array_intersect_key($report['rows'][0], array_flip(['d30', 'd60', 'd90', 'over90', 'balance'])));
        $this->assertSame(350000, $report['totals']['balance']);
    }

    public function test_reports_follow_module_permissions_and_export_as_csv_and_pdf(): void
    {
        $accountant = User::where('email', 'accounts@lms.test')->first();
        $groups = collect($this->actingAs($accountant, 'sanctum')->getJson('/api/erp/reports')->assertOk()->json('data'))->pluck('group')->all();

        $this->assertContains('Finance', $groups);
        $this->assertNotContains('Students', $groups);
        $this->getJson('/api/erp/reports/class-strength')->assertNotFound();
        $this->actingAs(User::where('email', 'teacher@lms.test')->first(), 'sanctum')->getJson('/api/erp/reports/day-book')->assertNotFound();

        $this->actingAs($this->admin)->get('/api/erp/reports/fee-collection/csv?group_by=method')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->actingAs($this->admin)->get('/api/erp/reports/staff-strength/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($accountant)->get(route('admin.reports'))->assertOk()->assertSee('Income &amp; Expenditure', false)->assertDontSee('Class Strength');
    }
}

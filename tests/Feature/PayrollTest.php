<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\EmployeeSalaryStructure;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PaymentMethod;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\SalaryComponent;
use App\Models\SalaryIncrement;
use App\Models\StaffAttendance;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\LeaveService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacherUser;

    private User $accountant;

    private Employee $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(10, 0));
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->teacherUser = User::where('email', 'teacher@lms.test')->first();
        $this->accountant = User::where('email', 'accounts@lms.test')->first();
        $this->teacher = Employee::where('user_id', $this->teacherUser->id)->first();
    }

    private function type(string $code): LeaveType
    {
        return LeaveType::where('code', $code)->first();
    }

    public function test_staff_apply_for_leave_and_hr_approval_uses_the_balance_and_marks_attendance(): void
    {
        LeaveRequest::query()->update(['status' => 'cancelled']);

        // Thu 29 Oct – Mon 2 Nov 2026: Sunday 1 Nov is not counted.
        $request = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/erp/my-hr/leave', ['leave_type_id' => $this->type('CL')->id, 'from_date' => '2026-10-29', 'to_date' => '2026-11-02', 'reason' => 'Visiting family'])
            ->assertCreated()
            ->assertJsonPath('data.days', 4)
            ->json('data');

        $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/erp/my-hr/leave', ['leave_type_id' => $this->type('SL')->id, 'from_date' => '2026-10-30', 'to_date' => '2026-10-30', 'reason' => 'Overlapping day'])
            ->assertUnprocessable();
        $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/erp/my-hr/leave', ['leave_type_id' => $this->type('CL')->id, 'from_date' => '2026-12-01', 'to_date' => '2026-12-12', 'reason' => 'Too many days'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.leave_type_id.0', 'Only 10 day(s) of Casual Leave are left; apply for 11 or choose unpaid leave.');
        $this->assertTrue(UserNotification::where('user_id', $this->admin->id)->where('event_key', 'leave_request')->exists());

        $this->actingAs($this->teacherUser, 'sanctum')->postJson("/api/erp/leave-requests/{$request['id']}/decide", ['approve' => true])->assertUnprocessable();
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/leave-requests/{$request['id']}/decide", ['approve' => true])->assertOk();

        $this->assertSame(4.0, LeaveBalance::where('employee_id', $this->teacher->id)->where('leave_type_id', $this->type('CL')->id)->value('used'));
        $this->assertSame(4, StaffAttendance::where('employee_id', $this->teacher->id)->where('status', 'leave')->count());
        $this->assertTrue(UserNotification::where('user_id', $this->teacherUser->id)->where('event_key', 'leave_decided')->exists());
        $this->assertDatabaseHas('activity_logs', ['module' => 'leave', 'action' => 'approved']);

        $this->actingAs($this->teacherUser, 'sanctum')->postJson("/api/erp/my-hr/leave/{$request['id']}/cancel")->assertOk();
        $this->assertSame(0.0, LeaveBalance::where('employee_id', $this->teacher->id)->where('leave_type_id', $this->type('CL')->id)->value('used'));
        $this->assertSame(0, StaffAttendance::where('employee_id', $this->teacher->id)->where('status', 'leave')->count());

        $this->actingAs($this->teacherUser, 'sanctum')->getJson('/api/erp/my-hr/leave')->assertOk()->assertJsonFragment(['name' => 'Maternity Leave']);
        $male = Employee::whereHas('gender', fn ($query) => $query->where('name', 'Male'))->first();
        $this->assertFalse(app(LeaveService::class)->typesFor($male)->contains('code', 'ML'));
    }

    public function test_a_month_of_payroll_from_payslips_to_ledger_and_payment(): void
    {
        StaffAttendance::create(['employee_id' => $this->teacher->id, 'date' => '2026-11-03', 'status' => 'absent', 'source' => 'manual']);

        $run = $this->actingAs($this->accountant, 'sanctum')->postJson('/api/erp/payroll-runs/generate', ['month' => '2026-11'])->assertCreated()->json('data');
        $slip = Payslip::where('payroll_run_id', $run['id'])->where('employee_id', $this->teacher->id)->with('items.component')->first();
        $lines = $slip->items->mapWithKeys(fn ($item): array => [$item->component->code => $item->amount]);
        $basic = $lines['BASIC'];

        $this->assertSame((int) round($basic * 0.45), $lines['HRA']);
        $this->assertSame(37000, $lines['EOBI']);
        $this->assertSame((int) round($basic * 0.05), $lines['PF']);
        $this->assertSame((int) round($slip->gross / $slip->working_days), $lines['ABSENT']);
        $this->assertSame(1.0, $slip->absent_days);
        $this->assertSame($slip->gross - $slip->deductions, $slip->net);

        $this->actingAs($this->accountant, 'sanctum')->postJson("/api/erp/payroll-runs/{$run['id']}/approve")->assertForbidden();
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/payroll-runs/{$run['id']}/approve")->assertOk();
        $this->actingAs($this->accountant, 'sanctum')->postJson('/api/erp/payroll-runs/generate', ['month' => '2026-11'])->assertUnprocessable();

        $this->actingAs($this->accountant, 'sanctum')->postJson("/api/erp/payroll-runs/{$run['id']}/post")->assertOk()->assertJsonPath('data.status', 'posted');
        $journal = JournalEntry::find(PayrollRun::find($run['id'])->journal_entry_id);
        $this->assertSame($journal->total_debit, $journal->total_credit);
        $payable = Account::where('code', '2200')->value('id');
        $this->assertSame(PayrollRun::find($run['id'])->total_net, (int) JournalLine::where('journal_entry_id', $journal->id)->where('account_id', $payable)->sum('credit'));

        $cash = PaymentMethod::where('code', 'BANK')->first();
        $this->actingAs($this->accountant, 'sanctum')
            ->postJson("/api/erp/payroll-runs/{$run['id']}/pay", ['payment_method_id' => $cash->id, 'paid_on' => '2026-11-30', 'reference' => 'HBL batch 11'])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');
        $this->assertSame('paid', $slip->refresh()->status);
        $this->assertTrue(UserNotification::where('user_id', $this->teacherUser->id)->where('event_key', 'salary_paid')->exists());

        $this->actingAs($this->teacherUser, 'sanctum')->getJson('/api/erp/my-hr/payslips')->assertOk()->assertJsonPath('data.0.net', $slip->net);
        $this->actingAs($this->teacherUser)->get("/api/erp/my-hr/payslips/{$slip->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
        $other = Payslip::where('payroll_run_id', $run['id'])->where('employee_id', '!=', $this->teacher->id)->value('id');
        $this->actingAs($this->teacherUser, 'sanctum')->getJson("/api/erp/my-hr/payslips/{$other}/pdf")->assertNotFound();
        $this->actingAs($this->accountant)->get("/api/erp/payroll-runs/{$run['id']}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_unpaid_leave_is_deducted_and_a_posted_run_can_be_reversed_with_a_narration(): void
    {
        LeaveRequest::query()->update(['status' => 'cancelled']);
        $leave = app(LeaveService::class)->apply($this->teacher, ['leave_type_id' => $this->type('UL')->id, 'from_date' => '2026-11-02', 'to_date' => '2026-11-03', 'reason' => 'Personal work'], $this->teacherUser);
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/leave-requests/{$leave->id}/decide", ['approve' => true])->assertOk();

        $run = $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/payroll-runs/generate', ['month' => '2026-11'])->json('data');
        $slip = Payslip::where('payroll_run_id', $run['id'])->where('employee_id', $this->teacher->id)->first();
        $this->assertSame(2.0, $slip->unpaid_leave_days);
        $this->assertTrue($slip->items()->where('salary_component_id', SalaryComponent::where('code', 'ABSENT')->value('id'))->exists());

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/payroll-runs/{$run['id']}/approve")->assertOk();
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/payroll-runs/{$run['id']}/post")->assertOk();
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/payroll-runs/{$run['id']}/reverse", ['reason' => 'short'])->assertUnprocessable();
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/payroll-runs/{$run['id']}/reverse", ['reason' => 'Wrong attendance for two teachers'])->assertOk()->assertJsonPath('data.status', 'draft');
        $this->assertTrue(JournalEntry::where('narration', 'like', 'Reversal of %Wrong attendance for two teachers')->exists());
    }

    public function test_a_loan_is_approved_by_an_admin_paid_out_and_recovered_through_payroll(): void
    {
        $loan = EmployeeLoan::where('employee_id', $this->teacher->id)->where('status', 'pending')->first();

        $this->actingAs($this->accountant, 'sanctum')->postJson("/api/erp/employee-loans/{$loan->id}/decide", ['approve' => true])->assertForbidden();
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/employee-loans/{$loan->id}/decide", ['approve' => true])->assertOk();
        $this->actingAs($this->accountant, 'sanctum')
            ->postJson("/api/erp/employee-loans/{$loan->id}/disburse", ['payment_method_id' => PaymentMethod::where('code', 'CASH')->value('id'), 'issued_on' => '2026-10-22'])
            ->assertOk();

        $loan->refresh();
        $this->assertSame('active', $loan->status);
        $this->assertSame(3, $loan->instalmentRows()->count());
        $this->assertSame($loan->amount, (int) JournalLine::where('journal_entry_id', $loan->journal_entry_id)->where('account_id', Account::where('code', '1400')->value('id'))->sum('debit'));

        $run = $this->actingAs($this->accountant, 'sanctum')->postJson('/api/erp/payroll-runs/generate', ['month' => $loan->start_month->format('Y-m')])->json('data');
        $this->assertTrue(Payslip::where('payroll_run_id', $run['id'])->where('employee_id', $this->teacher->id)->first()->items()->where('salary_component_id', SalaryComponent::where('code', 'LOAN')->value('id'))->where('amount', 1000000)->exists());

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/payroll-runs/{$run['id']}/approve")->assertOk();
        $this->actingAs($this->accountant, 'sanctum')->postJson("/api/erp/payroll-runs/{$run['id']}/post")->assertOk();
        $this->assertSame(2000000, $loan->refresh()->outstanding);
        $this->assertSame(1, $loan->instalmentRows()->where('status', 'deducted')->count());
    }

    public function test_a_new_salary_from_a_later_date_records_the_increment(): void
    {
        $current = $this->actingAs($this->accountant, 'sanctum')->getJson("/api/erp/employees/{$this->teacher->id}/salary-structure")->assertOk()->json('data.structure');

        $this->actingAs($this->accountant, 'sanctum')
            ->postJson("/api/erp/employees/{$this->teacher->id}/salary-structure", [
                'effective_from' => '2026-12-01', 'basic_salary' => $current['basic_salary'] + 1000000, 'remarks' => 'Annual increment',
                'items' => [['salary_component_id' => SalaryComponent::where('code', 'HRA')->value('id')]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.gross_salary', (int) round(($current['basic_salary'] + 1000000) * 1.45));

        $this->assertSame('2026-11-30', EmployeeSalaryStructure::find($current['id'])->effective_to->toDateString());
        $this->assertSame($current['basic_salary'] + 1000000, SalaryIncrement::where('employee_id', $this->teacher->id)->value('new_basic'));
    }

    public function test_hr_screens_and_my_hr_page(): void
    {
        foreach (['admin.hr.leave', 'admin.hr.leave-types', 'admin.hr.payroll', 'admin.hr.salaries', 'admin.hr.salary-setup', 'admin.hr.loans'] as $routeName) {
            $this->actingAs($this->admin)->get(route($routeName))->assertOk()->assertSee('js/admin-payroll.js', false);
        }

        $this->actingAs($this->teacherUser)->get(route('admin.hr.payroll'))->assertForbidden();
        $this->actingAs($this->teacherUser)->get(route('my-hr'))->assertOk()->assertSee('js/my-hr.js', false)->assertSee('Apply for Leave');
        $this->actingAs(User::where('email', 'parent@lms.test')->first(), 'sanctum')->getJson('/api/erp/my-hr/leave')->assertNotFound();
    }
}

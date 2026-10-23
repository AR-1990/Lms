<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Employee;
use App\Models\EmployeeSalaryStructure;
use App\Models\FiscalYear;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Lookup;
use App\Models\SalaryComponent;
use App\Models\TaxSlab;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\LeaveService;
use App\Services\LoanService;
use App\Services\PayrollService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Leave types, salary components and sample tax slabs (master data, all editable under HR), plus demo
 * salary structures for current staff, a pending leave request and a pending loan request.
 * The tax slabs are a sample to be checked against the current Finance Act.
 */
class HrPayrollSeeder extends Seeder
{
    public function run(LeaveService $leaveService, PayrollService $payrollService, LoanService $loanService): void
    {
        $female = Lookup::where('type', 'gender')->where('name', 'Female')->value('id');

        foreach ([
            ['Casual Leave', 'CL', 10, true, false, null, false, null],
            ['Sick Leave', 'SL', 8, true, false, null, true, null],
            ['Annual Leave', 'AL', 14, true, true, 7, false, null],
            ['Maternity Leave', 'ML', 90, true, false, null, true, $female],
            ['Unpaid Leave', 'UL', 0, false, false, null, false, null],
        ] as $position => [$name, $code, $days, $paid, $carry, $maxCarry, $document, $gender]) {
            LeaveType::updateOrCreate(['code' => $code], [
                'name' => $name, 'days_per_year' => $days, 'is_paid' => $paid, 'carry_forward' => $carry, 'max_carry_forward' => $maxCarry,
                'requires_document' => $document, 'applicable_gender_id' => $gender, 'sort_order' => $position, 'is_active' => true,
            ]);
        }

        $account = fn (string $code): ?int => Account::where('code', $code)->value('id');

        // [name, code, type, calculation, default (paisa or percent × 100), taxable, account code, system]
        foreach ([
            ['Basic Salary', 'BASIC', 'earning', 'fixed', 0, true, '5100', true],
            ['House Rent Allowance', 'HRA', 'earning', 'percent_of_basic', 4500, true, '5100', false],
            ['Medical Allowance', 'MED', 'earning', 'percent_of_basic', 1000, false, '5100', false],
            ['Conveyance Allowance', 'CONV', 'earning', 'fixed', 300000, true, '5100', false],
            ['Absent / Unpaid Leave Deduction', 'ABSENT', 'deduction', 'fixed', 0, false, '5100', true],
            ['Late Arrival Deduction', 'LATE', 'deduction', 'fixed', 0, false, '5100', true],
            ['Bonus', 'BONUS', 'earning', 'fixed', 0, true, '5100', false],
            ['Performance Incentive', 'INCENTIVE', 'earning', 'fixed', 0, true, '5100', false],
            ['Overtime', 'OVERTIME', 'earning', 'fixed', 0, true, '5100', false],
            ['Arrears', 'ARREARS', 'earning', 'fixed', 0, true, '5100', false],
            ['Fine', 'FINE', 'deduction', 'fixed', 0, false, '5100', false],
            ['Loan Instalment', 'LOAN', 'deduction', 'fixed', 0, false, '1400', true],
            ['Income Tax', 'TAX', 'deduction', 'fixed', 0, false, '2400', true],
            ['EOBI (employee share)', 'EOBI', 'deduction', 'fixed', 37000, false, '2410', false],
            ['Provident Fund', 'PF', 'deduction', 'percent_of_basic', 500, false, '2420', false],
        ] as $position => [$name, $code, $type, $calculation, $default, $taxable, $accountCode, $system]) {
            SalaryComponent::updateOrCreate(['code' => $code], [
                'name' => $name, 'type' => $type, 'calculation' => $calculation, 'default_value' => $default, 'is_taxable' => $taxable,
                'account_id' => $account($accountCode), 'is_system' => $system, 'sort_order' => $position, 'is_active' => true,
            ]);
        }

        $year = FiscalYear::where('is_current', true)->first();

        if ($year !== null && TaxSlab::where('fiscal_year_id', $year->id)->doesntExist()) {
            // Annual income in paisa: [min, max, fixed tax, rate % on income above min].
            foreach ([[0, 60000000, 0, 0], [60000001, 120000000, 0, 1], [120000001, 220000000, 600000, 11], [220000001, 320000000, 11600000, 23], [320000001, 410000000, 34600000, 30], [410000001, null, 61600000, 35]] as [$min, $max, $fixed, $rate]) {
                TaxSlab::create(['fiscal_year_id' => $year->id, 'min_income' => $min, 'max_income' => $max, 'fixed_tax' => $fixed, 'rate_percent' => $rate]);
            }
        }

        $admin = User::where('email', 'admin@lms.test')->first();

        if ($admin === null) {
            return;
        }

        Auth::setUser($admin);
        ActivityLogger::quietly(function () use ($admin, $payrollService, $leaveService, $loanService): void {
            $optional = SalaryComponent::whereIn('code', ['HRA', 'MED', 'CONV', 'EOBI', 'PF'])->pluck('id');

            foreach (Employee::whereIn('status', Employee::CURRENT_STATUSES)->orderBy('id')->get() as $index => $employee) {
                if (EmployeeSalaryStructure::where('employee_id', $employee->id)->exists()) {
                    continue;
                }

                $payrollService->saveStructure($employee, [
                    'effective_from' => $employee->joining_date?->toDateString() ?? now()->startOfYear()->toDateString(),
                    'basic_salary' => (6000000 + ($index % 6) * 1500000),
                    'items' => $optional->map(fn (int $id): array => ['salary_component_id' => $id])->all(),
                    'remarks' => 'Opening salary',
                ], $admin);
            }

            $teacher = Employee::whereHas('user', fn ($query) => $query->where('email', 'teacher@lms.test'))->first();

            if ($teacher !== null && LeaveRequest::where('employee_id', $teacher->id)->doesntExist()) {
                $leaveService->apply($teacher, [
                    'leave_type_id' => LeaveType::where('code', 'CL')->value('id'), 'from_date' => now()->addWeek()->startOfWeek()->toDateString(),
                    'to_date' => now()->addWeek()->startOfWeek()->addDay()->toDateString(), 'reason' => 'Family wedding in Faisalabad',
                ], $admin);
                $loanService->request($teacher, [
                    'loan_type_id' => Lookup::where('type', 'loan_type')->where('name', 'Salary Advance')->value('id'), 'amount' => 3000000, 'instalments' => 3,
                    'start_month' => now()->addMonth()->format('Y-m'), 'reason' => 'House repair',
                ], $admin);
            }
        });
        Auth::forgetUser();
    }
}

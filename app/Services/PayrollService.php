<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Employee;
use App\Models\EmployeeLoanInstalment;
use App\Models\EmployeeSalaryStructure;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\LeaveRequest;
use App\Models\PaymentMethod;
use App\Models\PayrollAdjustment;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\PayslipLeave;
use App\Models\SalaryComponent;
use App\Models\SalaryIncrement;
use App\Models\StaffAttendance;
use App\Models\SystemSetting;
use App\Models\TaxSlab;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;

/**
 * Payroll: salary components, salary structures and increments, tax slabs, and the monthly run —
 * generate payslips (attendance, lates under the late policy, unpaid leave, leave per type, one-off
 * adjustments such as bonus / overtime / fines, loan instalments, income tax) → approve → post the
 * salary journal (Dr salary expense / Cr deductions and salaries payable) → pay (Dr salaries payable /
 * Cr cash or bank). A posted but unpaid run can be reversed with a narration.
 */
class PayrollService
{
    public const SALARY_EXPENSE_ACCOUNT = '5100';

    public const SALARIES_PAYABLE_ACCOUNT = '2200';

    public function __construct(
        private JournalService $journalService,
        private LeaveService $leaveService,
        private NotificationService $notificationService,
        private ActivityLogger $activityLogger,
    ) {}

    /* ---------- Components & tax slabs ---------- */

    /**
     * @return array<string, mixed>
     */
    public function componentsDatatable(): array
    {
        return DataTables::eloquent(SalaryComponent::query()->with('account:id,code,name'))
            ->addColumn('account_name', fn (SalaryComponent $component): ?string => $component->account ? $component->account->code.' '.$component->account->name : null)
            ->removeColumn('account')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveComponent(?SalaryComponent $component, array $data): SalaryComponent
    {
        if ($component?->is_system) {
            $data = collect($data)->only(['name', 'account_id', 'is_taxable', 'sort_order'])->all();
        }

        return DB::transaction(function () use ($component, $data): SalaryComponent {
            if ($component === null) {
                return SalaryComponent::create($data);
            }

            $component->update($data);

            return $component;
        });
    }

    public function deleteComponent(SalaryComponent $component): void
    {
        if ($component->is_system || DB::table('employee_salary_structure_items')->where('salary_component_id', $component->id)->whereNull('deleted_at')->exists()) {
            throw ValidationException::withMessages(['component' => ['Built-in components and components used in salary structures cannot be deleted; make it inactive instead.']]);
        }

        DB::transaction(fn () => $component->delete());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveTaxSlab(?TaxSlab $slab, array $data): TaxSlab
    {
        return DB::transaction(function () use ($slab, $data): TaxSlab {
            if ($slab === null) {
                return TaxSlab::create($data);
            }

            $slab->update($data);

            return $slab;
        });
    }

    public function deleteTaxSlab(TaxSlab $slab): void
    {
        DB::transaction(fn () => $slab->delete());
    }

    /* ---------- Salary structures ---------- */

    /**
     * Employees with their current pay, for the "Salary Structures" grid.
     *
     * @return array<string, mixed>
     */
    public function structuresDatatable(): array
    {
        $today = now()->toDateString();
        $query = Employee::query()
            ->whereIn('status', Employee::CURRENT_STATUSES)
            ->with(['designation:id,name', 'department:id,name'])
            ->addSelect(['employees.*',
                'current_basic' => EmployeeSalaryStructure::select('basic_salary')->whereColumn('employee_id', 'employees.id')->whereDate('effective_from', '<=', $today)
                    ->where(fn ($inner) => $inner->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today))->latest('effective_from')->limit(1),
                'current_gross' => EmployeeSalaryStructure::select('gross_salary')->whereColumn('employee_id', 'employees.id')->whereDate('effective_from', '<=', $today)
                    ->where(fn ($inner) => $inner->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today))->latest('effective_from')->limit(1),
            ]);

        return DataTables::eloquent($query)
            ->addColumn('full_name', fn (Employee $employee): string => $employee->fullName())
            ->addColumn('designation_name', fn (Employee $employee): ?string => $employee->designation?->name)
            ->addColumn('department_name', fn (Employee $employee): ?string => $employee->department?->name)
            ->only(['id', 'employee_code', 'full_name', 'designation_name', 'department_name', 'current_basic', 'current_gross', 'first_name', 'last_name'])
            ->toArray();
    }

    public function currentStructure(Employee $employee, ?string $date = null): ?EmployeeSalaryStructure
    {
        $date ??= now()->toDateString();

        return EmployeeSalaryStructure::where('employee_id', $employee->id)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->with('items.component:id,name,code,type,calculation,default_value,is_taxable')
            ->latest('effective_from')
            ->first();
    }

    /**
     * Start a new structure from a date; the previous one ends the day before. A changed basic is
     * recorded as an increment.
     *
     * @param  array<string, mixed>  $data  effective_from, basic_salary, remarks, items[{salary_component_id, amount?}]
     */
    public function saveStructure(Employee $employee, array $data, User $user): EmployeeSalaryStructure
    {
        $components = SalaryComponent::whereIn('id', collect($data['items'] ?? [])->pluck('salary_component_id'))->get()->keyBy('id');
        $items = collect($data['items'] ?? [])->map(function (array $item) use ($components, $data): array {
            $component = $components[$item['salary_component_id']];
            $amount = $item['amount'] ?? null;

            if ($amount === null) {
                $amount = $component->calculation === 'percent_of_basic' ? (int) round($data['basic_salary'] * $component->default_value / 10000) : $component->default_value;
            }

            return ['salary_component_id' => $component->id, 'amount' => (int) $amount, 'type' => $component->type];
        })->reject(fn (array $item): bool => $item['amount'] <= 0);

        return DB::transaction(function () use ($employee, $data, $user, $items): EmployeeSalaryStructure {
            $previous = $this->currentStructure($employee, $data['effective_from']);

            if ($previous !== null && $previous->effective_from->toDateString() === $data['effective_from']) {
                throw ValidationException::withMessages(['effective_from' => ['A structure already starts on this date; choose a later date.']]);
            }

            $previous?->update(['effective_to' => Carbon::parse($data['effective_from'])->subDay()->toDateString()]);

            $structure = EmployeeSalaryStructure::create([
                'employee_id' => $employee->id, 'effective_from' => $data['effective_from'], 'basic_salary' => $data['basic_salary'],
                'gross_salary' => $data['basic_salary'] + $items->where('type', 'earning')->sum('amount'), 'remarks' => $data['remarks'] ?? null,
            ]);

            foreach ($items as $item) {
                $structure->items()->create(['salary_component_id' => $item['salary_component_id'], 'amount' => $item['amount']]);
            }

            if ($previous !== null && $previous->basic_salary !== (int) $data['basic_salary']) {
                SalaryIncrement::create([
                    'employee_id' => $employee->id, 'effective_from' => $data['effective_from'], 'old_basic' => $previous->basic_salary, 'new_basic' => $data['basic_salary'],
                    'increment_percent' => $previous->basic_salary > 0 ? round(($data['basic_salary'] - $previous->basic_salary) * 100 / $previous->basic_salary, 2) : null,
                    'reason' => $data['remarks'] ?? null, 'approved_by' => $user->id,
                ]);
            }

            return $structure->load('items.component:id,name,code,type');
        });
    }

    /* ---------- Payroll runs ---------- */

    /**
     * @return array<string, mixed>
     */
    public function runsDatatable(): array
    {
        return DataTables::eloquent(PayrollRun::query()->with('approver:id,name'))
            ->addColumn('approved_by_name', fn (PayrollRun $run): ?string => $run->approver?->name)
            ->editColumn('month', fn (PayrollRun $run): string => $run->month->toDateString())
            ->removeColumn('approver')
            ->toArray();
    }

    /**
     * Build (or rebuild, while draft) the payslips of a month for every current employee with a salary structure.
     */
    public function generate(string $month, User $user): PayrollRun
    {
        $start = Carbon::parse($month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $run = PayrollRun::whereDate('month', $start->toDateString())->first();

        if ($run !== null && $run->status !== 'draft') {
            throw ValidationException::withMessages(['month' => ["The {$start->format('F Y')} payroll is already {$run->status}."]]);
        }

        $workingDates = $this->leaveService->workingDates($start->toDateString(), $end->toDateString());
        $components = SalaryComponent::whereIn('code', SalaryComponent::SYSTEM_CODES)->get()->keyBy('code');

        return DB::transaction(function () use ($run, $start, $end, $user, $workingDates, $components): PayrollRun {
            $run ??= PayrollRun::create(['month' => $start->toDateString(), 'fiscal_year_id' => FiscalYear::whereDate('starts_on', '<=', $start)->whereDate('ends_on', '>=', $start)->value('id'), 'status' => 'draft']);

            foreach ($run->payslips()->get() as $old) {
                EmployeeLoanInstalment::where('payslip_id', $old->id)->update(['payslip_id' => null]);
                PayrollAdjustment::where('payslip_id', $old->id)->update(['payslip_id' => null]);
                $old->leaves()->get()->each->delete();
                $old->items()->get()->each->delete();
                $old->delete();
            }

            $policy = SystemSetting::query()->first(['late_grace_count', 'lates_per_deduction_day']);
            $employees = Employee::whereIn('status', Employee::CURRENT_STATUSES)->whereDate('joining_date', '<=', $end)->orderBy('first_name')->get();

            foreach ($employees as $employee) {
                $structure = $this->currentStructure($employee, $end->toDateString());

                if ($structure !== null) {
                    $this->makePayslip($run, $employee, $structure, $start, $end, $workingDates, $components, $policy);
                }
            }

            $this->refreshTotals($run);
            $this->activityLogger->record('payroll', 'generated', "{$user->name} generated the {$start->format('F Y')} payroll for {$run->employee_count} employees", $run);

            return $run;
        });
    }

    public function approve(PayrollRun $run, User $user): PayrollRun
    {
        $this->expectStatus($run, 'draft');

        if ($run->employee_count === 0) {
            throw ValidationException::withMessages(['status' => ['There are no payslips in this run.']]);
        }

        DB::transaction(function () use ($run, $user): void {
            $run->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()]);
            $run->payslips()->update(['status' => 'final']);
            $this->activityLogger->record('payroll', 'approved', "{$user->name} approved the {$run->month->format('F Y')} payroll (net Rs ".number_format($run->total_net / 100).')', $run);
        });

        return $run;
    }

    /**
     * The salary journal: Dr each earning account, Cr each deduction account and salaries payable (net).
     * Loan instalments of the month are marked deducted.
     */
    public function post(PayrollRun $run, User $user): PayrollRun
    {
        $this->expectStatus($run, 'approved');

        DB::transaction(function () use ($run, $user): void {
            $totals = DB::table('payslip_items')
                ->join('payslips', 'payslips.id', '=', 'payslip_items.payslip_id')
                ->join('salary_components', 'salary_components.id', '=', 'payslip_items.salary_component_id')
                ->where('payslips.payroll_run_id', $run->id)->whereNull('payslips.deleted_at')->whereNull('payslip_items.deleted_at')
                ->groupBy('payslip_items.type', 'salary_components.account_id', 'salary_components.name')
                ->get(['payslip_items.type', 'salary_components.account_id', 'salary_components.name', DB::raw('sum(payslip_items.amount) as total')]);

            $expense = $this->account(self::SALARY_EXPENSE_ACCOUNT);
            $lines = $totals->map(fn ($row): array => $row->type === 'earning'
                ? ['account_id' => $row->account_id ?: $expense, 'debit' => (int) $row->total, 'description' => $row->name]
                : ['account_id' => $row->account_id ?: $this->account(self::SALARIES_PAYABLE_ACCOUNT), 'credit' => (int) $row->total, 'description' => $row->name])
                ->filter(fn (array $line): bool => ($line['debit'] ?? $line['credit']) > 0)->values()->all();
            $lines[] = ['account_id' => $this->account(self::SALARIES_PAYABLE_ACCOUNT), 'credit' => $run->total_net, 'description' => 'Net salaries payable'];

            $entry = $this->journalService->record('JV', $run->month->copy()->endOfMonth()->toDateString(), "Salaries for {$run->month->format('F Y')} ({$run->employee_count} employees)", $lines, $run);

            foreach (EmployeeLoanInstalment::whereIn('payslip_id', $run->payslips()->select('id'))->with('loan')->get() as $instalment) {
                $instalment->update(['status' => 'deducted']);
                $loan = $instalment->loan;
                $outstanding = max(0, $loan->outstanding - $instalment->amount);
                $loan->update(['outstanding' => $outstanding, 'status' => $outstanding === 0 ? 'closed' : $loan->status]);
            }

            $run->update(['status' => 'posted', 'posted_at' => now(), 'journal_entry_id' => $entry->id]);
            $this->activityLogger->record('payroll', 'posted', "{$user->name} posted the {$run->month->format('F Y')} payroll as {$entry->voucher_no}", $run);
        });

        return $run;
    }

    /**
     * Pay the net salaries: Dr salaries payable, Cr the cash / bank account of the payment method.
     */
    public function pay(PayrollRun $run, User $user, int $paymentMethodId, string $paidOn, ?string $reference): PayrollRun
    {
        $this->expectStatus($run, 'posted');
        $method = PaymentMethod::findOrFail($paymentMethodId);

        if ($method->account_id === null) {
            throw ValidationException::withMessages(['payment_method_id' => ['This payment method has no cash or bank account.']]);
        }

        DB::transaction(function () use ($run, $user, $method, $paidOn, $reference): void {
            $entry = $this->journalService->record('PV', $paidOn, "Salary payment for {$run->month->format('F Y')}".($reference ? " ({$reference})" : ''), [
                ['account_id' => $this->account(self::SALARIES_PAYABLE_ACCOUNT), 'debit' => $run->total_net, 'description' => 'Net salaries'],
                ['account_id' => $method->account_id, 'credit' => $run->total_net, 'description' => $method->name],
            ], $run);

            $run->payslips()->update(['status' => 'paid', 'paid_on' => $paidOn, 'payment_method_id' => $method->id, 'bank_reference' => $reference]);
            $run->update(['status' => 'paid', 'paid_at' => now(), 'payment_journal_entry_id' => $entry->id]);
            $this->activityLogger->record('payroll', 'paid', "{$user->name} paid the {$run->month->format('F Y')} salaries by {$method->name} ({$entry->voucher_no})", $run);
        });

        $userIds = Employee::whereIn('id', $run->payslips()->select('employee_id'))->whereNotNull('user_id')->pluck('user_id')->all();
        $this->notificationService->notify($userIds, 'salary_paid', "Your {$run->month->format('F Y')} salary has been paid", 'Your payslip is ready in My HR.', route('my-hr'), $run);

        return $run;
    }

    /**
     * Undo a posted, unpaid run with a reversal voucher; instalments become due again and the run returns to draft.
     */
    public function reverse(PayrollRun $run, User $user, string $reason): PayrollRun
    {
        $this->expectStatus($run, 'posted');

        DB::transaction(function () use ($run, $user, $reason): void {
            $this->journalService->reverse(JournalEntry::findOrFail($run->journal_entry_id), $reason);

            foreach (EmployeeLoanInstalment::whereIn('payslip_id', $run->payslips()->select('id'))->where('status', 'deducted')->with('loan')->get() as $instalment) {
                $instalment->update(['status' => 'due']);
                $instalment->loan->update(['outstanding' => $instalment->loan->outstanding + $instalment->amount, 'status' => 'active']);
            }

            $run->payslips()->update(['status' => 'draft']);
            $run->update(['status' => 'draft', 'posted_at' => null, 'journal_entry_id' => null, 'approved_by' => null, 'approved_at' => null]);
            $this->activityLogger->record('payroll', 'reversed', "{$user->name} reversed the {$run->month->format('F Y')} payroll: {$reason}", $run);
        });

        return $run;
    }

    /**
     * @return array<string, mixed>
     */
    public function payslipsDatatable(PayrollRun $run): array
    {
        return DataTables::eloquent(Payslip::where('payroll_run_id', $run->id)->with('employee:id,first_name,last_name,employee_code'))
            ->addColumn('employee_name', fn (Payslip $payslip): ?string => $payslip->employee?->fullName())
            ->addColumn('employee_code', fn (Payslip $payslip): ?string => $payslip->employee?->employee_code)
            ->removeColumn('employee')
            ->toArray();
    }

    /**
     * Printable payslips, one per page.
     *
     * @param  Collection<int, Payslip>  $payslips
     */
    public function pdf(Collection $payslips, string $filename): Response
    {
        $payslips->load(['run:id,month,status', 'employee.designation:id,name', 'employee.department:id,name', 'items.component:id,name,code', 'paymentMethod:id,name', 'leaves.leaveType:id,name,is_paid']);

        return Pdf::loadView('pdf.payslips', ['payslips' => $payslips, 'schoolName' => SystemSetting::query()->value('school_name')])->setPaper('a4')->stream($filename);
    }

    /* ---------- Late policy and one-off adjustments ---------- */

    /**
     * @return array{late_grace_count: int, lates_per_deduction_day: int}
     */
    public function latePolicy(): array
    {
        $settings = SystemSetting::query()->first(['late_grace_count', 'lates_per_deduction_day']);

        return ['late_grace_count' => (int) ($settings?->late_grace_count ?? 0), 'lates_per_deduction_day' => (int) ($settings?->lates_per_deduction_day ?? 0)];
    }

    /**
     * @param  array{late_grace_count: int, lates_per_deduction_day: int}  $data
     * @return array{late_grace_count: int, lates_per_deduction_day: int}
     */
    public function saveLatePolicy(array $data, User $user): array
    {
        SystemSetting::query()->firstOrFail()->update($data);
        $this->activityLogger->record('payroll', 'late_policy', "{$user->name} set the late policy: first {$data['late_grace_count']} free, then every {$data['lates_per_deduction_day']} lates = 1 day", null);

        return $this->latePolicy();
    }

    /**
     * Days of pay taken for lates: the first few are free, then every N lates cost one day (0 = never).
     */
    public function lateDeductionDays(int $lateCount, ?SystemSetting $policy): float
    {
        $perDay = (int) ($policy?->lates_per_deduction_day ?? 0);

        return $perDay > 0 ? (float) intdiv(max(0, $lateCount - (int) ($policy?->late_grace_count ?? 0)), $perDay) : 0.0;
    }

    /**
     * @param  array{month?: string|null, employee_id?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function adjustmentsDatatable(array $filters): array
    {
        $query = PayrollAdjustment::query()
            ->select(['id', 'employee_id', 'month', 'salary_component_id', 'amount', 'note', 'payslip_id', 'created_at'])
            ->with(['employee:id,first_name,middle_name,last_name,employee_code', 'component:id,name,type', 'payslip:id,payroll_run_id,status', 'payslip.run:id,status'])
            ->when($filters['month'] ?? null, fn (Builder $query, string $month) => $query->whereDate('month', $month.'-01'))
            ->when($filters['employee_id'] ?? null, fn (Builder $query, int $id) => $query->where('employee_id', $id));

        return DataTables::eloquent($query)
            ->addColumn('employee_name', fn (PayrollAdjustment $row): ?string => $row->employee?->fullName())
            ->addColumn('employee_code', fn (PayrollAdjustment $row): ?string => $row->employee?->employee_code)
            ->addColumn('component_name', fn (PayrollAdjustment $row): ?string => $row->component?->name)
            ->addColumn('type', fn (PayrollAdjustment $row): ?string => $row->component?->type)
            ->addColumn('run_status', fn (PayrollAdjustment $row): ?string => $row->payslip?->run?->status)
            ->editColumn('month', fn (PayrollAdjustment $row): string => $row->month->format('Y-m'))
            ->filterColumn('employee_name', fn (Builder $query, string $keyword) => $query->whereHas('employee', fn (Builder $inner) => $inner->where(fn (Builder $match) => $match->where('first_name', 'like', "%{$keyword}%")->orWhere('last_name', 'like', "%{$keyword}%")->orWhere('employee_code', 'like', "%{$keyword}%"))))
            ->removeColumn('employee', 'component', 'payslip')
            ->toArray();
    }

    /**
     * Add or change a one-off amount. A draft payroll of that month is rebuilt so the payslip shows it at once.
     *
     * @param  array{employee_id: int, month: string, salary_component_id: int, amount: int, note?: string|null}  $data
     */
    public function saveAdjustment(?PayrollAdjustment $adjustment, array $data, User $user): PayrollAdjustment
    {
        $month = Carbon::parse($data['month'].'-01')->toDateString();
        $this->ensureMonthOpen($month);

        if ($adjustment !== null) {
            $this->ensureMonthOpen($adjustment->month->toDateString());
        }

        $adjustment ??= new PayrollAdjustment;
        $adjustment->fill([...$data, 'month' => $month, 'payslip_id' => null])->save();
        $this->rebuildDraft($month, $user);

        return $adjustment->refresh()->load('component:id,name,type');
    }

    public function deleteAdjustment(PayrollAdjustment $adjustment): void
    {
        $month = $adjustment->month->toDateString();
        $this->ensureMonthOpen($month);
        $adjustment->delete();

        if (auth()->user() !== null) {
            $this->rebuildDraft($month, auth()->user());
        }
    }

    private function ensureMonthOpen(string $month): void
    {
        $status = PayrollRun::whereDate('month', $month)->value('status');

        if ($status !== null && $status !== 'draft') {
            throw ValidationException::withMessages(['month' => ['The '.Carbon::parse($month)->format('F Y')." payroll is already {$status}; reverse it first or use next month."]]);
        }
    }

    private function rebuildDraft(string $month, User $user): void
    {
        if (PayrollRun::whereDate('month', $month)->where('status', 'draft')->exists()) {
            $this->generate(Carbon::parse($month)->format('Y-m'), $user);
        }
    }

    /* ---------- Internals ---------- */

    /**
     * @param  array<int, string>  $workingDates
     * @param  \Illuminate\Support\Collection<string, SalaryComponent>  $components
     */
    private function makePayslip(PayrollRun $run, Employee $employee, EmployeeSalaryStructure $structure, Carbon $start, Carbon $end, array $workingDates, $components, ?SystemSetting $policy): Payslip
    {
        $attendance = StaffAttendance::where('employee_id', $employee->id)->whereDate('date', '>=', $start->toDateString())->whereDate('date', '<=', $end->toDateString())->get(['status', 'late_minutes']);
        $marks = $attendance->pluck('status');
        $unpaidDays = 0.0;
        $leaveByType = [];

        foreach (LeaveRequest::where('employee_id', $employee->id)->where('status', 'approved')->with('leaveType:id,is_paid')
            ->whereDate('from_date', '<=', $end)->whereDate('to_date', '>=', $start)->get() as $leave) {
            $days = $leave->is_half_day ? 0.5 : count($this->leaveService->workingDates(max($leave->from_date, $start)->toDateString(), min($leave->to_date, $end)->toDateString()));
            $leaveByType[$leave->leave_type_id] = ($leaveByType[$leave->leave_type_id] ?? 0) + $days;

            if ($leave->leaveType?->is_paid === false) {
                $unpaidDays += $days;
            }
        }

        $workingDays = count($workingDates);
        $absentDays = $marks->filter(fn (string $status): bool => $status === 'absent')->count() + $marks->filter(fn (string $status): bool => $status === 'half_day')->count() * 0.5;
        $leaveDays = $marks->filter(fn (string $status): bool => $status === 'leave')->count();

        $lines = collect([['component' => $components['BASIC'], 'type' => 'earning', 'amount' => $structure->basic_salary, 'note' => null]]);

        foreach ($structure->items as $item) {
            $lines->push(['component' => $item->component, 'type' => $item->component->type, 'amount' => $item->amount, 'note' => null]);
        }

        // A day's pay comes from the regular monthly pay, before one-off adjustments.
        $dayRate = $workingDays > 0 ? (int) $lines->where('type', 'earning')->sum('amount') / $workingDays : 0;
        $chargeable = $absentDays + $unpaidDays;

        if ($chargeable > 0 && $dayRate > 0) {
            $lines->push(['component' => $components['ABSENT'], 'type' => 'deduction', 'amount' => (int) round($dayRate * $chargeable), 'note' => "{$chargeable} day(s)"]);
        }

        $lateCount = $marks->filter(fn (string $status): bool => $status === 'late')->count();
        $lateDays = $this->lateDeductionDays($lateCount, $policy);

        if ($lateDays > 0 && $dayRate > 0 && isset($components['LATE'])) {
            $lines->push(['component' => $components['LATE'], 'type' => 'deduction', 'amount' => (int) round($dayRate * $lateDays), 'note' => "{$lateCount} late(s) = {$lateDays} day(s)"]);
        }

        $adjustments = PayrollAdjustment::where('employee_id', $employee->id)->whereDate('month', $start->toDateString())->whereNull('payslip_id')->with('component')->get();

        foreach ($adjustments as $adjustment) {
            $lines->push(['component' => $adjustment->component, 'type' => $adjustment->component->type, 'amount' => $adjustment->amount, 'note' => $adjustment->note]);
        }

        $gross = (int) $lines->where('type', 'earning')->sum('amount');

        $instalments = EmployeeLoanInstalment::whereHas('loan', fn (Builder $query) => $query->where('employee_id', $employee->id)->where('status', 'active'))
            ->whereDate('due_month', $start->toDateString())->where('status', 'due')->get();

        if ($instalments->isNotEmpty()) {
            $lines->push(['component' => $components['LOAN'], 'type' => 'deduction', 'amount' => (int) $instalments->sum('amount'), 'note' => $instalments->count().' instalment(s)']);
        }

        if (! $structure->items->contains(fn ($item): bool => $item->component->code === 'TAX')) {
            $tax = $this->monthlyTax((int) $lines->where('type', 'earning')->filter(fn (array $line): bool => (bool) $line['component']->is_taxable)->sum('amount'), $run->fiscal_year_id);

            if ($tax > 0) {
                $lines->push(['component' => $components['TAX'], 'type' => 'deduction', 'amount' => $tax, 'note' => null]);
            }
        }

        $deductions = min($gross, (int) $lines->where('type', 'deduction')->sum('amount'));

        $payslip = Payslip::create([
            'payroll_run_id' => $run->id, 'employee_id' => $employee->id, 'working_days' => $workingDays,
            'present_days' => max(0, $workingDays - $absentDays - $leaveDays - $unpaidDays), 'leave_days' => $leaveDays, 'unpaid_leave_days' => $unpaidDays,
            'absent_days' => $absentDays, 'late_count' => $lateCount, 'late_minutes' => (int) $attendance->sum('late_minutes'), 'late_deduction_days' => $lateDays, 'gross' => $gross, 'deductions' => $deductions, 'net' => $gross - $deductions, 'status' => 'draft',
        ]);

        foreach ($lines->filter(fn (array $line): bool => $line['amount'] > 0) as $line) {
            $payslip->items()->create(['salary_component_id' => $line['component']->id, 'type' => $line['type'], 'amount' => $line['amount'], 'note' => $line['note']]);
        }

        $instalments->each(fn (EmployeeLoanInstalment $instalment) => $instalment->update(['payslip_id' => $payslip->id]));
        $adjustments->each(fn (PayrollAdjustment $adjustment) => $adjustment->update(['payslip_id' => $payslip->id]));

        foreach ($leaveByType as $leaveTypeId => $days) {
            PayslipLeave::create(['payslip_id' => $payslip->id, 'leave_type_id' => $leaveTypeId, 'days' => $days]);
        }

        return $payslip;
    }

    /**
     * Income tax for the month from the year's slabs, on the annualised taxable pay.
     */
    private function monthlyTax(int $monthlyTaxable, ?int $fiscalYearId): int
    {
        $annual = $monthlyTaxable * 12;
        $slab = TaxSlab::where('fiscal_year_id', $fiscalYearId)->where('min_income', '<=', $annual)
            ->where(fn (Builder $query) => $query->whereNull('max_income')->orWhere('max_income', '>=', $annual))->orderByDesc('min_income')->first();

        if ($slab === null) {
            return 0;
        }

        return (int) round(($slab->fixed_tax + ($annual - $slab->min_income) * $slab->rate_percent / 100) / 12);
    }

    private function refreshTotals(PayrollRun $run): void
    {
        $payslips = $run->payslips()->get(['gross', 'deductions', 'net']);
        $run->update([
            'total_gross' => (int) $payslips->sum('gross'), 'total_deductions' => (int) $payslips->sum('deductions'),
            'total_net' => (int) $payslips->sum('net'), 'employee_count' => $payslips->count(),
        ]);
    }

    private function expectStatus(PayrollRun $run, string $status): void
    {
        if ($run->status !== $status) {
            throw ValidationException::withMessages(['status' => ["This payroll is {$run->status}; it must be {$status} for this step."]]);
        }
    }

    private function account(string $code): int
    {
        return Account::where('code', $code)->firstOrFail()->id;
    }
}

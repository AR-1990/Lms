<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Employee;
use App\Models\EmployeeExit;
use App\Models\EmployeeLoan;
use App\Models\ExitClearance;
use App\Models\LeaveType;
use App\Models\Lookup;
use App\Models\PaymentMethod;
use App\Models\Payslip;
use App\Models\SystemSetting;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;

/**
 * Staff leaving: start the exit, each area clears it, the final settlement (salary for the last days, leave
 * encashment, loan recovery, extras) is paid as one payment voucher, the employee is closed and can get an
 * experience letter.
 */
class EmployeeExitService
{
    private const SALARY_EXPENSE = '5100';

    private const EMPLOYEE_LOANS = '1400';

    private const OTHER_INCOME = '4900';

    public function __construct(
        private PayrollService $payrollService,
        private LeaveService $leaveService,
        private AssetService $assetService,
        private EmployeeService $employeeService,
        private JournalService $journalService,
        private ActivityLogger $activityLogger,
    ) {}

    /**
     * @param  array{status?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function datatable(array $filters): array
    {
        $query = EmployeeExit::query()
            ->with(['employee:id,employee_code,first_name,middle_name,last_name,designation_id', 'employee.designation:id,name', 'reason:id,name'])
            ->withCount(['clearances', 'clearances as cleared_count' => fn ($query) => $query->where('cleared', true)])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->select(['id', 'employee_id', 'exit_type', 'reason_id', 'notice_date', 'last_working_day', 'status', 'final_settlement_amount', 'settlement_paid_on']);

        return DataTables::eloquent($query)
            ->addColumn('employee_name', fn (EmployeeExit $exit): ?string => $exit->employee?->fullName())
            ->addColumn('employee_code', fn (EmployeeExit $exit): ?string => $exit->employee?->employee_code)
            ->addColumn('designation_name', fn (EmployeeExit $exit): ?string => $exit->employee?->designation?->name)
            ->addColumn('reason_name', fn (EmployeeExit $exit): ?string => $exit->reason?->name)
            ->editColumn('last_working_day', fn (EmployeeExit $exit): string => $exit->last_working_day->toDateString())
            ->filterColumn('employee_name', fn ($query, string $keyword) => $query->whereHas('employee', fn ($employee) => $employee->where('first_name', 'like', "%{$keyword}%")->orWhere('last_name', 'like', "%{$keyword}%")->orWhere('employee_code', 'like', "%{$keyword}%")))
            ->removeColumn('employee', 'reason')
            ->toArray();
    }

    /**
     * @param  array{employee_id: int, exit_type: string, reason_id?: int|null, notice_date?: string|null, last_working_day: string, remarks?: string|null}  $data
     *
     * @throws ValidationException when the employee has already left or an exit is already open
     */
    public function start(array $data): EmployeeExit
    {
        $employee = Employee::findOrFail($data['employee_id']);

        if (! in_array($employee->status, Employee::CURRENT_STATUSES, true)) {
            throw ValidationException::withMessages(['employee_id' => ["{$employee->fullName()} has already left."]]);
        }

        if (EmployeeExit::where('employee_id', $employee->id)->where('status', EmployeeExit::STATUS_CLEARANCE)->exists()) {
            throw ValidationException::withMessages(['employee_id' => ["An exit for {$employee->fullName()} is already open."]]);
        }

        return DB::transaction(function () use ($data): EmployeeExit {
            $exit = EmployeeExit::create([...$data, 'status' => EmployeeExit::STATUS_CLEARANCE]);

            Lookup::ofType('exit_clearance_area')->active()->orderBy('sort_order')->pluck('id')
                ->each(fn (int $areaId) => ExitClearance::create(['employee_exit_id' => $exit->id, 'area_id' => $areaId]));

            return $this->find($exit);
        });
    }

    public function find(EmployeeExit $exit): EmployeeExit
    {
        return $exit->load([
            'employee:id,employee_code,first_name,middle_name,last_name,designation_id,department_id,joining_date,status',
            'employee.designation:id,name', 'employee.department:id,name', 'reason:id,name',
            'clearances.area:id,name', 'clearances.clearedBy:id,name', 'lines',
        ]);
    }

    public function clear(ExitClearance $clearance, bool $cleared, ?string $remarks, User $user): ExitClearance
    {
        $exit = EmployeeExit::findOrFail($clearance->employee_exit_id);
        $this->ensureOpen($exit);

        $clearance->update(['cleared' => $cleared, 'remarks' => $remarks, 'cleared_by' => $cleared ? $user->id : null, 'cleared_at' => $cleared ? now() : null]);

        return $clearance->load(['area:id,name', 'clearedBy:id,name']);
    }

    /**
     * The settlement as it stands: worked days of the last month (when no payslip covers them), unused
     * carried-forward leave paid at basic / 30 a day, open loans recovered — plus what HR adds.
     *
     * @return array{lines: array<int, array{source: string, label: string, type: string, amount: int}>, earnings: int, deductions: int, net: int, assets_held: array<int, mixed>, blocking: array<int, string>}
     */
    public function settlement(EmployeeExit $exit, array $extra = []): array
    {
        $employee = $exit->employee()->firstOrFail();
        $lastDay = $exit->last_working_day;
        $structure = $this->payrollService->currentStructure($employee, $lastDay->toDateString());
        $lines = [];

        $covered = Payslip::where('employee_id', $employee->id)
            ->whereHas('run', fn ($query) => $query->whereDate('month', $lastDay->copy()->startOfMonth()->toDateString())->where('status', '!=', 'reversed'))
            ->exists();

        if ($structure !== null && ! $covered) {
            $days = $lastDay->day;
            $lines[] = ['source' => 'salary', 'label' => "Salary for {$days} day(s) of ".$lastDay->format('F Y'), 'type' => 'earning', 'amount' => (int) round($structure->gross_salary * $days / $lastDay->daysInMonth)];
        }

        if ($structure !== null) {
            $this->leaveService->typesFor($employee)
                ->filter(fn (LeaveType $type): bool => $type->is_paid && $type->carry_forward)
                ->each(function (LeaveType $type) use (&$lines, $structure, $employee, $lastDay): void {
                    $days = (float) $this->leaveService->balanceFor($employee, $type, $lastDay->year)->remaining();

                    if ($days > 0) {
                        $lines[] = ['source' => 'leave', 'label' => "{$type->name} encashment ({$days} day(s))", 'type' => 'earning', 'amount' => (int) round($structure->basic_salary / 30 * $days)];
                    }
                });
        }

        $loans = (int) EmployeeLoan::where('employee_id', $employee->id)->where('status', 'active')->sum('outstanding');

        if ($loans > 0) {
            $lines[] = ['source' => 'loan', 'label' => 'Loan recovery', 'type' => 'deduction', 'amount' => $loans];
        }

        foreach ($extra as $line) {
            $lines[] = ['source' => 'manual', 'label' => $line['label'], 'type' => $line['type'], 'amount' => (int) $line['amount']];
        }

        $earnings = (int) collect($lines)->where('type', 'earning')->sum('amount');
        $deductions = (int) collect($lines)->where('type', 'deduction')->sum('amount');
        $assets = $this->assetService->heldBy($employee)->all();
        $pending = $exit->clearances()->with('area:id,name')->where('cleared', false)->get()->map(fn (ExitClearance $clearance): string => $clearance->area?->name ?? 'An area');

        return [
            'lines' => $lines,
            'earnings' => $earnings,
            'deductions' => $deductions,
            'net' => $earnings - $deductions,
            'assets_held' => $assets,
            'blocking' => array_values(array_filter([
                $pending->isNotEmpty() ? 'Clearance pending: '.$pending->implode(', ').'.' : null,
                $assets !== [] ? count($assets).' school asset(s) still held — take them back under Assets first.' : null,
                ($books = app(LibraryService::class)->openFor('employee', $employee->id)->count()) > 0 ? $books.' library book(s) not returned yet.' : null,
                $earnings - $deductions < 0 ? 'The employee owes Rs '.number_format(($deductions - $earnings) / 100).'; receive it first or adjust the lines.' : null,
            ])),
        ];
    }

    /**
     * Pay the settlement (Dr Salary Expense / Cr Employee Loans, Other Income, cash or bank), close the
     * loans and the employee.
     *
     * @param  array{payment_method_id: int, paid_on: string, lines?: array<int, array{label: string, type: string, amount: int}>, exit_interview_notes?: string|null}  $data
     *
     * @throws ValidationException while clearance or assets are pending, or the net is below zero
     */
    public function settle(EmployeeExit $exit, array $data, User $user): EmployeeExit
    {
        $this->ensureOpen($exit);
        $settlement = $this->settlement($exit, $data['lines'] ?? []);

        if ($settlement['blocking'] !== []) {
            throw ValidationException::withMessages(['settlement' => $settlement['blocking']]);
        }

        $method = PaymentMethod::where('is_active', true)->findOrFail($data['payment_method_id']);
        $employee = $exit->employee()->firstOrFail();

        return DB::transaction(function () use ($exit, $data, $user, $settlement, $method, $employee): EmployeeExit {
            foreach ($settlement['lines'] as $index => $line) {
                $exit->lines()->create([...$line, 'sort_order' => $index]);
            }

            $party = ['party_type' => Employee::class, 'party_id' => $employee->id];
            $account = fn (string $code): int => Account::where('code', $code)->firstOrFail()->id;
            $loan = (int) collect($settlement['lines'])->where('source', 'loan')->sum('amount');
            $otherDeductions = $settlement['deductions'] - $loan;
            $lines = array_values(array_filter([
                $settlement['earnings'] > 0 ? ['account_id' => $account(self::SALARY_EXPENSE), 'debit' => $settlement['earnings'], 'description' => 'Final settlement', ...$party] : null,
                $loan > 0 ? ['account_id' => $account(self::EMPLOYEE_LOANS), 'credit' => $loan, 'description' => 'Loan recovered on exit', ...$party] : null,
                $otherDeductions > 0 ? ['account_id' => $account(self::OTHER_INCOME), 'credit' => $otherDeductions, 'description' => 'Deductions on exit', ...$party] : null,
                $settlement['net'] > 0 ? ['account_id' => $method->account_id, 'credit' => $settlement['net'], 'description' => $method->name] : null,
            ]));

            $entry = $lines === [] ? null : $this->journalService->record('PV', $data['paid_on'], "Final settlement — {$employee->fullName()} ({$employee->employee_code})", $lines, $exit);

            EmployeeLoan::where('employee_id', $employee->id)->where('status', 'active')->get()
                ->each(fn (EmployeeLoan $open) => $open->update(['outstanding' => 0, 'status' => 'closed']));

            $exit->update([
                'status' => EmployeeExit::STATUS_SETTLED,
                'final_settlement_amount' => $settlement['net'],
                'settlement_paid_on' => $data['paid_on'],
                'payment_method_id' => $method->id,
                'journal_entry_id' => $entry?->id,
                'exit_interview_notes' => $data['exit_interview_notes'] ?? $exit->exit_interview_notes,
            ]);

            $this->employeeService->changeStatus($employee, EmployeeExit::TYPES[$exit->exit_type], $exit->last_working_day->toDateString(), 'Final settlement paid ('.str_replace('_', ' ', $exit->exit_type).').');
            $this->activityLogger->record('hr', 'settled', "{$user->name} paid the final settlement of {$employee->fullName()}: Rs ".number_format($settlement['net'] / 100), $exit);

            return $this->find($exit);
        });
    }

    public function cancel(EmployeeExit $exit, User $user): EmployeeExit
    {
        $this->ensureOpen($exit);
        $exit->update(['status' => EmployeeExit::STATUS_CANCELLED]);
        $this->activityLogger->record('hr', 'cancelled', "{$user->name} cancelled the exit of employee #{$exit->employee_id}", $exit);

        return $this->find($exit);
    }

    public function experienceLetter(EmployeeExit $exit, User $user): Response
    {
        if ($exit->status !== EmployeeExit::STATUS_SETTLED) {
            throw ValidationException::withMessages(['exit' => ['The experience letter is available once the final settlement is paid.']]);
        }

        $exit = $this->find($exit);
        $this->activityLogger->record('hr', 'printed', "{$user->name} printed the experience letter of {$exit->employee->fullName()}", $exit);

        return Pdf::loadView('pdf.experience-letter', [
            'exit' => $exit,
            'settings' => SystemSetting::query()->first(['school_name', 'school_tagline', 'school_address', 'school_phone']),
            'logo' => app(SystemSettingsService::class)->logoFile(),
        ])->setPaper('a4')->stream('experience-letter-'.$exit->employee->employee_code.'.pdf');
    }

    private function ensureOpen(EmployeeExit $exit): void
    {
        if ($exit->status !== EmployeeExit::STATUS_CLEARANCE) {
            throw ValidationException::withMessages(['exit' => ['This exit is already '.$exit->status.'.']]);
        }
    }
}

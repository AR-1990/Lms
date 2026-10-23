<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

/**
 * Staff loans and salary advances: request → admin approval → paid out (Dr employee loans & advances /
 * Cr cash or bank) → monthly instalments deducted through payroll → closed.
 */
class LoanService
{
    public const LOANS_ACCOUNT = '1400';

    public function __construct(
        private JournalService $journalService,
        private NotificationService $notificationService,
        private ActivityLogger $activityLogger,
    ) {}

    /**
     * @param  array{status?: string|null, employee_id?: int|null}  $filters
     */
    public function query(array $filters = []): Builder
    {
        return EmployeeLoan::query()
            ->with(['employee:id,first_name,last_name,employee_code,user_id', 'loanType:id,name'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['employee_id'] ?? null, fn (Builder $query, int $employeeId) => $query->where('employee_id', $employeeId));
    }

    /**
     * @param  array{status?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function datatable(array $filters): array
    {
        return DataTables::eloquent($this->query($filters))
            ->addColumn('employee_name', fn (EmployeeLoan $loan): ?string => $loan->employee?->fullName())
            ->addColumn('employee_code', fn (EmployeeLoan $loan): ?string => $loan->employee?->employee_code)
            ->addColumn('loan_type_name', fn (EmployeeLoan $loan): ?string => $loan->loanType?->name)
            ->editColumn('start_month', fn (EmployeeLoan $loan): string => $loan->start_month->toDateString())
            ->removeColumn('employee', 'loanType')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data  loan_type_id, amount, instalments, start_month (Y-m), reason
     */
    public function request(Employee $employee, array $data, User $user): EmployeeLoan
    {
        if (EmployeeLoan::where('employee_id', $employee->id)->whereIn('status', ['pending', 'approved'])->exists()) {
            throw ValidationException::withMessages(['amount' => ['There is already a loan request waiting for this employee.']]);
        }

        $instalments = max(1, (int) $data['instalments']);
        $loan = DB::transaction(fn (): EmployeeLoan => EmployeeLoan::create([
            'employee_id' => $employee->id, 'loan_type_id' => $data['loan_type_id'] ?? null, 'amount' => $data['amount'], 'instalments' => $instalments,
            'instalment_amount' => (int) ceil($data['amount'] / $instalments), 'start_month' => Carbon::parse($data['start_month'].'-01')->toDateString(),
            'outstanding' => 0, 'status' => 'pending', 'reason' => $data['reason'] ?? null,
        ]));

        $admins = User::where('is_active', true)->whereHas('roles.permissions', fn (Builder $query) => $query->where('slug', 'approve-requests'))->pluck('id')->all();
        $this->notificationService->notify(array_diff($admins, [$user->id]), 'loan_request', "{$employee->fullName()} requested a loan of Rs ".number_format($data['amount'] / 100),
            $data['reason'] ?? null, route('admin.hr.loans'), $loan);

        return $loan;
    }

    /**
     * Administrators approve or reject a pending request (the applicant cannot decide their own).
     */
    public function decide(User $user, EmployeeLoan $loan, bool $approve, ?string $remarks): EmployeeLoan
    {
        if ($loan->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ["This loan is already {$loan->status}."]]);
        }

        if ($loan->employee?->user_id === $user->id) {
            throw ValidationException::withMessages(['status' => ['You cannot approve your own loan.']]);
        }

        DB::transaction(function () use ($user, $loan, $approve, $remarks): void {
            $loan->update(['status' => $approve ? 'approved' : 'rejected', 'approved_by' => $user->id, 'approved_at' => now(), 'review_remarks' => $remarks]);
            $this->activityLogger->record('payroll', $approve ? 'approved' : 'rejected', "{$user->name} ".($approve ? 'approved' : 'rejected')." loan #{$loan->id} of {$loan->employee->fullName()} (Rs ".number_format($loan->amount / 100).')', $loan);
        });

        if ($loan->employee->user_id) {
            $this->notificationService->notify([$loan->employee->user_id], 'loan_decided', 'Your loan request was '.($approve ? 'approved ✅' : 'not approved'), $remarks, route('my-hr'), $loan);
        }

        return $loan;
    }

    /**
     * Pay out an approved loan and schedule its monthly instalments.
     */
    public function disburse(User $user, EmployeeLoan $loan, int $paymentMethodId, string $issuedOn): EmployeeLoan
    {
        if ($loan->status !== 'approved') {
            throw ValidationException::withMessages(['status' => ['Only an approved loan can be paid out.']]);
        }

        $method = PaymentMethod::findOrFail($paymentMethodId);

        if ($method->account_id === null) {
            throw ValidationException::withMessages(['payment_method_id' => ['This payment method has no cash or bank account.']]);
        }

        DB::transaction(function () use ($user, $loan, $method, $issuedOn): void {
            $party = ['party_type' => Employee::class, 'party_id' => $loan->employee_id];
            $entry = $this->journalService->record('PV', $issuedOn, "Loan #{$loan->id} paid to {$loan->employee->fullName()}", [
                ['account_id' => Account::where('code', self::LOANS_ACCOUNT)->firstOrFail()->id, 'debit' => $loan->amount, 'description' => 'Staff loan', ...$party],
                ['account_id' => $method->account_id, 'credit' => $loan->amount, 'description' => $method->name],
            ], $loan);

            $remaining = $loan->amount;

            for ($index = 0; $index < $loan->instalments; $index++) {
                $amount = $index === $loan->instalments - 1 ? $remaining : min($loan->instalment_amount, $remaining);
                $loan->instalmentRows()->create(['due_month' => $loan->start_month->copy()->addMonthsNoOverflow($index)->toDateString(), 'amount' => $amount, 'status' => 'due']);
                $remaining -= $amount;
            }

            $loan->update(['status' => 'active', 'issued_on' => $issuedOn, 'outstanding' => $loan->amount, 'payment_method_id' => $method->id, 'journal_entry_id' => $entry->id]);
            $this->activityLogger->record('payroll', 'disbursed', "{$user->name} paid out loan #{$loan->id} ({$entry->voucher_no})", $loan);
        });

        return $loan->load('instalmentRows');
    }
}

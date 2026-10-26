<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\FeeInvoice;
use App\Models\FeeInvoiceBatch;
use App\Models\FeeInvoiceFine;
use App\Models\LateFeeRule;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

/**
 * Fee work that runs by itself (PLAN.md §9): late fines on overdue challans every night under the late fee
 * rules, and the month's challans generated on the day the school picks. Both are logged and the fee staff
 * are told what happened.
 */
class FeeAutomationService
{
    public function __construct(
        private FeeInvoiceService $invoiceService,
        private JournalService $journalService,
        private NotificationService $notificationService,
        private ActivityLogger $activityLogger,
    ) {}

    /* ---------- Late fee rules ---------- */

    /**
     * @return array<string, mixed>
     */
    public function rulesDatatable(): array
    {
        return DataTables::eloquent(LateFeeRule::query()->with('feeHead:id,name')->withSum('fines', 'amount')->withCount('fines'))
            ->addColumn('fee_head_name', fn (LateFeeRule $rule): ?string => $rule->feeHead?->name)
            ->removeColumn('feeHead')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveRule(?LateFeeRule $rule, array $data): LateFeeRule
    {
        $rule ??= new LateFeeRule;
        $rule->fill([...$data, 'grace_days' => $data['grace_days'] ?? 0])->save();

        return $rule->load('feeHead:id,name');
    }

    public function deleteRule(LateFeeRule $rule): void
    {
        $rule->delete();
    }

    /**
     * Fine a rule asks for on a challan as of a date (0 inside the grace days). A per-day fine grows each day.
     */
    public function fineFor(LateFeeRule $rule, FeeInvoice $invoice, Carbon $today): int
    {
        $daysLate = (int) $invoice->due_date->copy()->addDays($rule->grace_days)->diffInDays($today, false);

        if ($daysLate <= 0) {
            return 0;
        }

        $fine = match ($rule->calculation) {
            'per_day' => $rule->amount * $daysLate,
            'percent' => (int) round(($invoice->total - $invoice->fine_total) * $rule->amount / 10000),
            default => $rule->amount,
        };

        return $rule->max_amount ? min($fine, $rule->max_amount) : $fine;
    }

    /**
     * Nightly: add what each active rule asks for and has not added yet (a fixed or percent fine once, a per-day
     * fine as the days go by) to every unpaid, overdue challan. Each fine is posted Dr Receivable / Cr fine income.
     *
     * @return array{challans: int, amount: int}
     */
    /**
     * Morning reminder to administrators and accounts staff (everyone who manages fees) of students
     * whose challans were not paid by the due date, naming those who became overdue yesterday.
     *
     * @return array{students: int, amount: int, new: int}
     */
    public function overdueAlert(?Carbon $today = null): array
    {
        $today ??= today();
        $overdue = FeeInvoice::query()
            ->whereIn('status', FeeInvoice::OPEN_STATUSES)
            ->where('balance', '>', 0)
            ->whereNotNull('student_id')
            ->whereDate('due_date', '<', $today->toDateString());

        $summary = ['students' => (clone $overdue)->distinct()->count('student_id'), 'amount' => (int) (clone $overdue)->sum('balance'), 'new' => 0];

        if ($summary['students'] === 0) {
            return $summary;
        }

        $newlyOverdue = (clone $overdue)->whereDate('due_date', $today->copy()->subDay()->toDateString())
            ->with(['student:id,first_name,middle_name,last_name', 'enrollment.schoolClass:id,name'])
            ->get(['id', 'student_id', 'enrollment_id'])
            ->unique('student_id');
        $summary['new'] = $newlyOverdue->count();

        // Each family is reminded once, the morning after the due date.
        (clone $overdue)->whereDate('due_date', $today->copy()->subDay()->toDateString())->with('student:id,first_name,middle_name,last_name')->get(['id', 'invoice_no', 'student_id', 'balance', 'due_date'])
            ->each(fn (FeeInvoice $invoice) => $this->notificationService->notifyEvent('fee_overdue', $this->notificationService->familyUserIds([$invoice->student_id])->all(), [
                'student_name' => $invoice->student?->fullName(), 'invoice_no' => $invoice->invoice_no, 'amount' => 'Rs '.number_format($invoice->balance / 100), 'due_date' => $invoice->due_date->format('d M Y'),
            ], $invoice, route('family.child', $invoice->student_id)));

        $names = $newlyOverdue->take(5)->map(fn (FeeInvoice $invoice): string => $invoice->student?->fullName().($invoice->enrollment?->schoolClass ? ' ('.$invoice->enrollment->schoolClass->name.')' : ''))->implode(', ');
        $body = 'Rs '.number_format($summary['amount'] / 100).' is unpaid after the due date.'
            .($summary['new'] > 0 ? " Overdue since yesterday: {$names}".($summary['new'] > 5 ? ' and '.($summary['new'] - 5).' more' : '').'.' : '');

        $this->notificationService->notify($this->feeStaff(), 'fees_overdue', "{$summary['students']} student(s) have not paid by the due date", $body, route('accounts.fees.challans', ['status' => 'overdue']), null, $summary['new'] > 0);

        return $summary;
    }

    public function applyLateFees(?Carbon $today = null): array
    {
        $today ??= today();
        $challans = 0;
        $amount = 0;

        foreach (LateFeeRule::where('is_active', true)->with('feeHead:id,income_account_id')->get() as $rule) {
            FeeInvoice::query()
                ->whereIn('status', FeeInvoice::OPEN_STATUSES)->where('balance', '>', 0)
                ->when($rule->invoice_type, fn (Builder $query, string $type) => $query->where('invoice_type', $type))
                ->whereDate('due_date', '<', $today->copy()->subDays($rule->grace_days)->toDateString())
                ->withSum(['fines as rule_fined' => fn (Builder $query) => $query->where('late_fee_rule_id', $rule->id)], 'amount')
                ->chunkById(200, function ($invoices) use ($rule, $today, &$challans, &$amount): void {
                    foreach ($invoices as $invoice) {
                        $add = $this->fineFor($rule, $invoice, $today) - (int) $invoice->rule_fined;

                        if ($add > 0) {
                            $this->addFine($invoice, $rule, $add, $today);
                            $challans++;
                            $amount += $add;
                        }
                    }
                });
        }

        if ($challans > 0) {
            $this->activityLogger->record('fee_invoices', 'late_fees', 'Late fines of Rs '.number_format($amount / 100)." added to {$challans} overdue challan(s)");
            $this->notificationService->notify($this->feeStaff(), 'late_fees_applied', 'Late fines added', 'Rs '.number_format($amount / 100)." on {$challans} overdue challan(s).", route('accounts.fees.challans'));
        }

        return ['challans' => $challans, 'amount' => $amount];
    }

    /* ---------- Automatic monthly challans ---------- */

    /**
     * @return array{fee_auto_generate_day: int|null}
     */
    public function settings(): array
    {
        return ['fee_auto_generate_day' => SystemSetting::query()->value('fee_auto_generate_day')];
    }

    /**
     * @param  array{fee_auto_generate_day: int|null}  $data
     * @return array{fee_auto_generate_day: int|null}
     */
    public function saveSettings(array $data, User $user): array
    {
        SystemSetting::query()->firstOrFail()->update($data);
        $day = $data['fee_auto_generate_day'];
        $this->activityLogger->record('fee_invoices', 'automation', $day ? "{$user->name} set monthly challans to be generated on day {$day}" : "{$user->name} switched off automatic monthly challans");

        return $this->settings();
    }

    /**
     * Daily: on the chosen day (the last day in short months) the month's challans are generated for every class
     * of the current session, once. Returns the batch, or null when it is not the day or it already ran.
     */
    public function autoGenerate(?Carbon $today = null): ?FeeInvoiceBatch
    {
        $today ??= today();
        $day = (int) SystemSetting::query()->value('fee_auto_generate_day');
        $session = AcademicSession::where('is_current', true)->first();

        if ($day < 1 || $session === null || $today->day !== min($day, $today->daysInMonth)) {
            return null;
        }

        $month = $today->copy()->startOfMonth();
        $alreadyRan = FeeInvoiceBatch::where('academic_session_id', $session->id)->whereNull('class_id')->whereDate('billing_month', $month->toDateString())->exists();

        if ($alreadyRan) {
            return null;
        }

        $batch = $this->invoiceService->generateMonth($session->id, null, $month);
        $this->notificationService->notify($this->feeStaff(), 'challans_generated', $month->format('F Y').' challans are ready', "{$batch->generated_count} challans, Rs ".number_format($batch->total_amount / 100).' in total.', route('accounts.fees.challans'), $batch);

        return $batch;
    }

    /* ---------- Internals ---------- */

    private function addFine(FeeInvoice $invoice, LateFeeRule $rule, int $amount, Carbon $today): void
    {
        DB::transaction(function () use ($invoice, $rule, $amount, $today): void {
            $income = $rule->feeHead?->income_account_id ?? $this->invoiceService->account('4300');
            $entry = $this->journalService->record('JV', $today->toDateString(), "{$rule->name} on challan {$invoice->invoice_no}", [
                ['account_id' => $this->invoiceService->account(FeeInvoiceService::RECEIVABLE_ACCOUNT), 'debit' => $amount, 'description' => $invoice->invoice_no, ...$this->invoiceService->party($invoice)],
                ['account_id' => $income, 'credit' => $amount, 'description' => $rule->name],
            ], $invoice);

            FeeInvoiceFine::create(['fee_invoice_id' => $invoice->id, 'late_fee_rule_id' => $rule->id, 'amount' => $amount, 'applied_on' => $today->toDateString(), 'journal_entry_id' => $entry->id]);

            $invoice->fine_total += $amount;
            $invoice->total += $amount;
            $this->invoiceService->settle($invoice);
        });
    }

    /**
     * Active users who manage fees (told about overdue challans, fines and online payment proofs).
     *
     * @return array<int, int>
     */
    public function feeStaff(): array
    {
        return User::where('is_active', true)->whereHas('roles.permissions', fn (Builder $query) => $query->where('slug', 'manage-fees'))->pluck('id')->all();
    }
}

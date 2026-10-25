<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AdmissionApplication;
use App\Models\BankAccount;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\FeeHead;
use App\Models\FeeInvoice;
use App\Models\FeeInvoiceBatch;
use App\Models\FeePayment;
use App\Models\FeePaymentAllocation;
use App\Models\FeeRefund;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\FeeWaiver;
use App\Models\JournalEntry;
use App\Models\LateFeeRule;
use App\Models\Student;
use App\Models\StudentConcession;
use App\Models\SystemSetting;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;

/**
 * Fee challans: built from the class fee structure, discounted by approved concessions,
 * and posted to the ledger the moment they are issued (PLAN.md §2.3).
 */
class FeeInvoiceService
{
    /**
     * Built-in accounts (their codes cannot change, see AccountService::update).
     */
    public const RECEIVABLE_ACCOUNT = '1300';

    public const ADVANCE_ACCOUNT = '2300';

    public const CONCESSION_ACCOUNT = '5400';

    public function __construct(
        private NumberSequenceService $numberSequenceService,
        private JournalService $journalService,
        private ActivityLogger $activityLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function query(array $filters = []): Builder
    {
        return FeeInvoice::query()
            ->select(['id', 'invoice_no', 'student_id', 'enrollment_id', 'admission_application_id', 'academic_session_id', 'invoice_type', 'billing_month', 'issue_date', 'due_date', 'subtotal', 'concession_total', 'waived_total', 'total', 'paid_total', 'balance', 'status', 'created_at'])
            ->with(['student:id,admission_no,first_name,last_name', 'application:id,application_no,first_name,last_name'])
            ->when($filters['student_id'] ?? null, fn (Builder $query, int $id) => $query->where('student_id', $id))
            ->when(array_key_exists('student_ids', $filters), fn (Builder $query) => $query->whereIn('student_id', $filters['student_ids']))
            ->when($filters['admission_application_id'] ?? null, fn (Builder $query, int $id) => $query->where('admission_application_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => match ($status) {
                'unpaid' => $query->whereIn('status', FeeInvoice::OPEN_STATUSES),
                'overdue' => $query->whereIn('status', FeeInvoice::OPEN_STATUSES)->where('balance', '>', 0)->whereDate('due_date', '<', today()->toDateString()),
                default => $query->where('status', $status),
            })
            ->when($filters['invoice_type'] ?? null, fn (Builder $query, string $type) => $query->where('invoice_type', $type))
            ->when($filters['billing_month'] ?? null, fn (Builder $query, string $month) => $query->whereDate('billing_month', Carbon::parse($month)->startOfMonth()->toDateString()))
            ->when($filters['class_id'] ?? null, fn (Builder $query, int $classId) => $query->whereIn('enrollment_id', Enrollment::where('class_id', $classId)->select('id')));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function datatable(array $filters): array
    {
        return DataTables::eloquent($this->query($filters))
            ->escapeColumns([])
            ->addColumn('payer_name', fn (FeeInvoice $invoice): string => $invoice->payerName())
            ->addColumn('payer_ref', fn (FeeInvoice $invoice): ?string => $invoice->student?->admission_no ?? $invoice->application?->application_no)
            ->filterColumn('payer_name', fn (Builder $query, string $keyword) => $query->where(fn (Builder $inner) => $inner
                ->where('invoice_no', 'like', "%{$keyword}%")
                ->orWhereHas('student', fn (Builder $student) => $student->whereAny(['first_name', 'last_name', 'admission_no'], 'like', "%{$keyword}%"))
                ->orWhereHas('application', fn (Builder $application) => $application->whereAny(['first_name', 'last_name', 'application_no'], 'like', "%{$keyword}%"))))
            ->editColumn('issue_date', fn (FeeInvoice $invoice): string => $invoice->issue_date->toDateString())
            ->editColumn('due_date', fn (FeeInvoice $invoice): string => $invoice->due_date->toDateString())
            ->editColumn('billing_month', fn (FeeInvoice $invoice): ?string => $invoice->billing_month?->format('Y-m'))
            ->removeColumn('student', 'application')
            ->toArray();
    }

    /**
     * Students whose challans a parent or student login may see (null = staff, sees everyone).
     *
     * @return array<int, int>|null
     */
    public function visibleStudentIds(User $user): ?array
    {
        if ($user->hasPermission('manage-fees')) {
            return null;
        }

        return Student::ownIdsFor($user);
    }

    public function find(FeeInvoice $invoice): FeeInvoice
    {
        return $invoice->load([
            'items.feeHead:id,name,code', 'student:id,admission_no,first_name,last_name', 'application:id,application_no,first_name,last_name',
            'academicSession:id,name', 'journalEntry:id,voucher_no', 'allocations.payment:id,receipt_no,received_on,status', 'waivers.approvedBy:id,name', 'canceller:id,name',
            'fines.rule:id,name',
        ]);
    }

    /* ---------- Building challans ---------- */

    /**
     * Registration (`on_application`) or admission (`on_admission`) challan for an applicant.
     * Returns null when the class fee structure has no such charges.
     */
    public function forApplication(AdmissionApplication $application, string $chargeOn): ?FeeInvoice
    {
        $items = $this->structureItems($application->academic_session_id, $application->class_applied_id)
            ->filter(fn (FeeStructureItem $item): bool => $item->charge_on === $chargeOn && $item->is_mandatory);

        if ($items->isEmpty()) {
            return null;
        }

        $issueDate = now()->startOfDay();

        return $this->issue([
            'admission_application_id' => $application->id,
            'student_id' => $application->student_id,
            'academic_session_id' => $application->academic_session_id,
            'invoice_type' => $chargeOn === 'on_application' ? 'registration' : 'admission',
            'issue_date' => $issueDate->toDateString(),
            'due_date' => $issueDate->copy()->addDays((int) ($items->max('due_in_days') ?? 7))->toDateString(),
        ], $this->linesFrom($items), null);
    }

    /**
     * Monthly challan for one enrolled student. Null when nothing is due or the month is already billed.
     */
    public function monthly(Enrollment $enrollment, Carbon $billingMonth, ?FeeInvoiceBatch $batch = null): ?FeeInvoice
    {
        $month = $billingMonth->copy()->startOfMonth();
        $student = $enrollment->student;

        $alreadyBilled = FeeInvoice::where('student_id', $enrollment->student_id)
            ->where('invoice_type', 'monthly')
            ->whereDate('billing_month', $month->toDateString())
            ->where('status', '!=', 'cancelled')
            ->exists();

        if ($alreadyBilled || $student === null || $student->current_status !== 'active') {
            return null;
        }

        $sessionStart = $enrollment->academicSession?->starts_on;
        $isNewStudent = $sessionStart !== null && $student->admission_date->greaterThanOrEqualTo($sessionStart);

        $items = $this->structureItems($enrollment->academic_session_id, $enrollment->class_id)
            ->filter(fn (FeeStructureItem $item): bool => $item->charge_on === 'recurring'
                && $item->is_mandatory
                && $item->appliesToMonth($month->month)
                && (! $item->applies_to_new_students_only || $isNewStudent));

        $transport = app(TransportService::class)->monthlyLines($student, $month);

        if ($items->isEmpty() && $transport->isEmpty()) {
            return null;
        }

        $dueDay = min((int) ($items->min('due_day') ?? 10), $month->daysInMonth);

        return $this->issue([
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'academic_session_id' => $enrollment->academic_session_id,
            'invoice_type' => 'monthly',
            'billing_month' => $month->toDateString(),
            'issue_date' => now()->toDateString(),
            'due_date' => $month->copy()->day($dueDay)->max(now()->startOfDay())->toDateString(),
            'fee_invoice_batch_id' => $batch?->id,
        ], $this->linesFrom($items, $month)->concat($transport), $student);
    }

    /**
     * One-off challan with hand-picked heads (books, trip, fine…).
     *
     * @param  array{student_id: int, issue_date: string, due_date: string, notes?: string|null, items: array<int, array{fee_head_id: int, amount: int, description?: string|null}>}  $data
     */
    public function manual(array $data): FeeInvoice
    {
        $student = Student::findOrFail($data['student_id']);
        $enrollment = $student->currentEnrollment;

        if ($enrollment === null) {
            throw ValidationException::withMessages(['student_id' => ['This student is not enrolled in the current session.']]);
        }

        $heads = FeeHead::whereIn('id', array_column($data['items'], 'fee_head_id'))->get()->keyBy('id');

        return $this->issue([
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'academic_session_id' => $enrollment->academic_session_id,
            'invoice_type' => 'other',
            'issue_date' => $data['issue_date'],
            'due_date' => $data['due_date'],
            'notes' => $data['notes'] ?? null,
        ], collect($data['items'])->map(fn (array $line): array => [
            'head' => $heads[$line['fee_head_id']],
            'amount' => (int) $line['amount'],
            'description' => $line['description'] ?? $heads[$line['fee_head_id']]->name,
        ]), $student);
    }

    /**
     * Exam fee challan for one enrolment; null when the student already has one for this exam.
     */
    public function forExam(Exam $exam, Enrollment $enrollment): ?FeeInvoice
    {
        if (FeeInvoice::where('exam_id', $exam->id)->where('enrollment_id', $enrollment->id)->where('status', '!=', 'cancelled')->exists()) {
            return null;
        }

        $head = FeeHead::findOrFail($exam->fee_head_id);

        return $this->issue([
            'student_id' => $enrollment->student_id,
            'enrollment_id' => $enrollment->id,
            'academic_session_id' => $enrollment->academic_session_id,
            'invoice_type' => 'exam',
            'exam_id' => $exam->id,
            'issue_date' => now()->toDateString(),
            'due_date' => max($exam->fee_due_date?->toDateString() ?? now()->addDays(10)->toDateString(), now()->toDateString()),
            'notes' => "Exam fee — {$exam->name}",
        ], collect([['head' => $head, 'amount' => (int) $exam->exam_fee_amount, 'description' => "{$head->name} — {$exam->name}"]]), $enrollment->student);
    }

    /**
     * Monthly challans for every active enrolment of a session (optionally one class).
     */
    public function generateMonth(int $sessionId, ?int $classId, Carbon $billingMonth): FeeInvoiceBatch
    {
        return DB::transaction(function () use ($sessionId, $classId, $billingMonth): FeeInvoiceBatch {
            $batch = FeeInvoiceBatch::create([
                'academic_session_id' => $sessionId,
                'billing_month' => $billingMonth->copy()->startOfMonth()->toDateString(),
                'class_id' => $classId,
                'status' => 'processing',
                'generated_by' => auth()->id(),
            ]);

            $generated = 0;
            $skipped = 0;
            $total = 0;

            Enrollment::with(['student', 'academicSession'])
                ->where('academic_session_id', $sessionId)
                ->where('status', 'active')
                ->when($classId, fn (Builder $query, int $id) => $query->where('class_id', $id))
                ->chunkById(200, function (Collection $enrollments) use ($billingMonth, $batch, &$generated, &$skipped, &$total): void {
                    foreach ($enrollments as $enrollment) {
                        $invoice = $this->monthly($enrollment, $billingMonth, $batch);

                        if ($invoice === null) {
                            $skipped++;

                            continue;
                        }

                        $generated++;
                        $total += $invoice->total;
                    }
                });

            $batch->update(['generated_count' => $generated, 'skipped_count' => $skipped, 'total_amount' => $total, 'status' => 'done']);

            $this->activityLogger->record('fee_invoices', 'batch_generated', (auth()->user()?->name ?? 'System')." generated {$generated} challans for ".$billingMonth->format('F Y'), $batch);

            return $batch;
        });
    }

    /* ---------- After issue ---------- */

    /**
     * Cancel an untouched challan; its ledger posting is reversed.
     *
     * @throws ValidationException when anything was paid or waived
     */
    public function cancel(FeeInvoice $invoice, string $reason): FeeInvoice
    {
        $advance = $invoice->allocations()->where('from_advance', true)->with('journalEntry')->get();

        // A challan paid only from the student's advance can still be cancelled; the advance goes back to them.
        if (! in_array($invoice->status, [...FeeInvoice::OPEN_STATUSES, 'paid'], true) || $invoice->paid_total !== (int) $advance->sum('amount') || $invoice->waived_total > 0) {
            throw ValidationException::withMessages(['invoice' => ['Only an unpaid challan can be cancelled. Reverse its receipts first.']]);
        }

        return DB::transaction(function () use ($invoice, $reason, $advance): FeeInvoice {
            foreach ($advance as $allocation) {
                if ($allocation->journalEntry?->status === JournalEntry::STATUS_POSTED) {
                    $this->journalService->reverse($allocation->journalEntry, "Challan {$invoice->invoice_no} cancelled; advance returned");
                }
            }

            if ($invoice->journal_entry_id !== null) {
                $this->journalService->reverse($invoice->journalEntry, "Challan {$invoice->invoice_no} cancelled: {$reason}");
            }

            $invoice->update(['status' => 'cancelled', 'cancelled_reason' => $reason, 'cancelled_by' => auth()->id(), 'cancelled_at' => now(), 'balance' => 0]);

            return $this->find($invoice);
        });
    }

    /**
     * Write off part of a challan (e.g. hardship); posted as concession expense.
     *
     * @throws ValidationException when the amount is more than what is owed
     */
    public function waive(FeeInvoice $invoice, int $amount, string $reason): FeeInvoice
    {
        if (! in_array($invoice->status, FeeInvoice::OPEN_STATUSES, true) || $amount > $invoice->balance) {
            throw ValidationException::withMessages(['amount' => ['The waiver cannot be more than the unpaid balance ('.$this->money($invoice->balance).').']]);
        }

        return DB::transaction(function () use ($invoice, $amount, $reason): FeeInvoice {
            $entry = $this->journalService->record('JV', now()->toDateString(), "Fee waived on {$invoice->invoice_no}: {$reason}", [
                ['account_id' => $this->account(self::CONCESSION_ACCOUNT), 'debit' => $amount],
                ['account_id' => $this->account(self::RECEIVABLE_ACCOUNT), 'credit' => $amount, ...$this->party($invoice)],
            ], $invoice);

            FeeWaiver::create([
                'fee_invoice_id' => $invoice->id,
                'amount' => $amount,
                'reason' => $reason,
                'approved_by' => auth()->id(),
                'journal_entry_id' => $entry->id,
            ]);

            $invoice->waived_total += $amount;
            $this->settle($invoice);

            $this->activityLogger->record('fee_invoices', 'waived', (auth()->user()?->name ?? 'System').' waived '.$this->money($amount)." on {$invoice->invoice_no}", $invoice, ['waived' => ['old' => null, 'new' => $this->money($amount).' — '.$reason]]);

            return $this->find($invoice);
        });
    }

    /**
     * Recalculate the balance and status after a payment, reversal or waiver.
     */
    public function settle(FeeInvoice $invoice): void
    {
        $invoice->balance = max($invoice->total - $invoice->paid_total - $invoice->waived_total, 0);
        $invoice->status = $invoice->balance === 0 ? 'paid' : ($invoice->paid_total + $invoice->waived_total > 0 ? 'partially_paid' : 'posted');
        $invoice->saveQuietly();
    }

    /**
     * Money a student paid in advance pays a new challan straight away (oldest advance first): an allocation
     * marked from_advance, posted Dr Student Advances / Cr Student Receivable.
     */
    public function applyAdvance(FeeInvoice $invoice): void
    {
        if ($invoice->student_id === null || $invoice->balance <= 0 || ! in_array($invoice->status, FeeInvoice::OPEN_STATUSES, true)) {
            return;
        }

        $payments = FeePayment::where('student_id', $invoice->student_id)->where('status', 'posted')->where('advance_amount', '>', 0)
            ->withSum(['allocations as advance_used' => fn (Builder $query) => $query->where('from_advance', true)->whereHas('invoice', fn (Builder $inner) => $inner->where('status', '!=', 'cancelled'))], 'amount')
            ->orderBy('received_on')->orderBy('id')->lockForUpdate()->get(['id', 'receipt_no', 'advance_amount']);
        // Advance already paid back to the family uses up the oldest receipts first.
        $refunded = (int) FeeRefund::where('student_id', $invoice->student_id)->where('source', 'advance')->where('status', FeeRefund::STATUS_POSTED)->sum('amount');

        foreach ($payments as $payment) {
            $left = $payment->advance_amount - (int) $payment->advance_used;
            $paidBack = min($left, $refunded);
            $refunded -= $paidBack;
            $left -= $paidBack;
            $take = min($left, $invoice->balance);

            if ($take <= 0) {
                continue;
            }

            $entry = $this->journalService->record('JV', now()->toDateString(), "Advance from receipt {$payment->receipt_no} used on challan {$invoice->invoice_no}", [
                ['account_id' => $this->account(self::ADVANCE_ACCOUNT), 'debit' => $take, 'description' => 'Advance used', ...$this->party($invoice)],
                ['account_id' => $this->account(self::RECEIVABLE_ACCOUNT), 'credit' => $take, 'description' => $invoice->invoice_no, ...$this->party($invoice)],
            ], $invoice);

            FeePaymentAllocation::create(['fee_payment_id' => $payment->id, 'fee_invoice_id' => $invoice->id, 'amount' => $take, 'from_advance' => true, 'journal_entry_id' => $entry->id]);
            $invoice->paid_total += $take;
            $this->settle($invoice);
            $this->activityLogger->record('fee_invoices', 'advance_applied', $this->money($take)." of advance from receipt {$payment->receipt_no} paid challan {$invoice->invoice_no}", $invoice);

            if ($invoice->balance === 0) {
                break;
            }
        }
    }

    /**
     * Unused advance a student has paid (shown on the challan and the collection desk).
     */
    public function advanceBalance(int $studentId): int
    {
        $paid = (int) FeePayment::where('student_id', $studentId)->where('status', 'posted')->sum('advance_amount');
        $used = (int) FeePaymentAllocation::where('from_advance', true)
            ->whereHas('payment', fn (Builder $query) => $query->where('student_id', $studentId)->where('status', 'posted'))
            ->whereHas('invoice', fn (Builder $query) => $query->where('status', '!=', 'cancelled'))
            ->sum('amount');
        $refunded = (int) FeeRefund::where('student_id', $studentId)->where('source', 'advance')->where('status', FeeRefund::STATUS_POSTED)->sum('amount');

        return max(0, $paid - $used - $refunded);
    }

    /**
     * Printable challans: one landscape page per challan with the bank, school and student copies side by side.
     *
     * @param  EloquentCollection<int, FeeInvoice>  $invoices
     */
    public function pdf(EloquentCollection $invoices, string $filename, User $user): Response
    {
        $invoices->load(['items', 'fines.rule:id,name', 'student:id,admission_no,first_name,middle_name,last_name', 'application:id,application_no,first_name,last_name', 'enrollment.schoolClass:id,name', 'enrollment.section:id,name']);
        $rules = LateFeeRule::where('is_active', true)->get(['name', 'grace_days', 'calculation', 'amount', 'max_amount', 'invoice_type']);
        $this->activityLogger->record('fee_invoices', 'printed', "{$user->name} printed ".$invoices->count().' challan(s)');

        return Pdf::loadView('pdf.challans', [
            'invoices' => $invoices,
            'rules' => $rules,
            'settings' => SystemSetting::query()->first(['school_name', 'school_address', 'school_phone']),
            'logo' => app(SystemSettingsService::class)->logoFile(),
            'bank' => BankAccount::with('bank:id,name')->where('is_default_for_fees', true)->first(['id', 'bank_id', 'branch', 'account_title', 'account_number', 'iban']),
        ])->setPaper('a4', 'landscape')->stream($filename);
    }

    /**
     * Ledger account id of a built-in account.
     */
    public function account(string $code): int
    {
        return Account::where('code', $code)->firstOrFail()->id;
    }

    /**
     * Sub-ledger party of a challan: the student, or the applicant before enrolment.
     *
     * @return array{party_type: string, party_id: int}
     */
    public function party(FeeInvoice $invoice): array
    {
        return $invoice->student_id !== null
            ? ['party_type' => Student::class, 'party_id' => $invoice->student_id]
            : ['party_type' => AdmissionApplication::class, 'party_id' => (int) $invoice->admission_application_id];
    }

    /* ---------- Internals ---------- */

    /**
     * @return Collection<int, FeeStructureItem>
     */
    private function structureItems(int $sessionId, int $classId): Collection
    {
        $structure = FeeStructure::where('academic_session_id', $sessionId)->where('class_id', $classId)->where('is_active', true)->first();

        return $structure === null
            ? collect()
            : $structure->items()->with(['feeHead', 'months'])->get()->filter(fn (FeeStructureItem $item): bool => (bool) $item->feeHead?->is_active)->values();
    }

    /**
     * @param  Collection<int, FeeStructureItem>  $items
     * @return Collection<int, array{head: FeeHead, amount: int, description: string}>
     */
    private function linesFrom(Collection $items, ?Carbon $month = null): Collection
    {
        return $items->map(fn (FeeStructureItem $item): array => [
            'head' => $item->feeHead,
            'amount' => $item->amount,
            'description' => $item->feeHead->name.($month ? ' — '.$month->format('M Y') : ''),
        ])->values();
    }

    /**
     * Create the challan, apply concessions and post it to the ledger in one transaction.
     *
     * @param  array<string, mixed>  $attributes
     * @param  Collection<int, array{head: FeeHead, amount: int, description: string}>  $lines
     */
    private function issue(array $attributes, Collection $lines, ?Student $student): FeeInvoice
    {
        return DB::transaction(function () use ($attributes, $lines, $student): FeeInvoice {
            $concessions = $student === null ? collect() : StudentConcession::with('concessionType')
                ->where('student_id', $student->id)
                ->where('status', 'approved')
                ->whereDate('valid_from', '<=', $attributes['issue_date'])
                ->where(fn (Builder $query) => $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', $attributes['issue_date']))
                ->get();

            $items = $lines->map(function (array $line) use ($concessions): array {
                $best = $line['head']->is_concession_allowed
                    ? $concessions
                        ->filter(fn (StudentConcession $concession): bool => $concession->fee_head_id === null || $concession->fee_head_id === $line['head']->id)
                        ->sortByDesc(fn (StudentConcession $concession): int => $concession->discountOn($line['amount']))
                        ->first()
                    : null;
                $discount = $best?->discountOn($line['amount']) ?? 0;

                return [
                    'fee_head_id' => $line['head']->id,
                    'description' => $line['description'],
                    'amount' => $line['amount'],
                    'concession_amount' => $discount,
                    'student_concession_id' => $discount > 0 ? $best->id : null,
                    'net_amount' => $line['amount'] - $discount,
                    'head' => $line['head'],
                    'concession' => $discount > 0 ? $best : null,
                ];
            });

            $subtotal = (int) $items->sum('amount');
            $concessionTotal = (int) $items->sum('concession_amount');
            $total = $subtotal - $concessionTotal;

            $invoice = FeeInvoice::create([
                ...$attributes,
                'invoice_no' => $this->numberSequenceService->next('fee_invoice'),
                'subtotal' => $subtotal,
                'concession_total' => $concessionTotal,
                'fine_total' => 0,
                'waived_total' => 0,
                'total' => $total,
                'paid_total' => 0,
                'balance' => $total,
                'status' => $total === 0 ? 'paid' : 'posted',
                'posted_at' => now(),
            ]);

            $invoice->items()->createMany($items->map(fn (array $item): array => collect($item)->except(['head', 'concession'])->all())->all());

            if ($subtotal > 0) {
                $entry = $this->journalService->record('FIV', $attributes['issue_date'], "Fee challan {$invoice->invoice_no} — {$invoice->payerName()}", $this->postingLines($invoice, $items), $invoice);
                $invoice->forceFill(['journal_entry_id' => $entry->id])->saveQuietly();
            }

            $this->applyAdvance($invoice);

            if ($student !== null && $invoice->balance > 0) {
                $notifications = app(NotificationService::class);
                $notifications->notifyEvent('challan_issued', $notifications->familyUserIds([$student->id])->all(), [
                    'student_name' => $student->fullName(), 'invoice_no' => $invoice->invoice_no, 'amount' => $this->money($invoice->balance), 'due_date' => $invoice->due_date->format('d M Y'),
                ], $invoice, route('family.child', $student));
            }

            return $invoice;
        });
    }

    /**
     * Dr Student Receivable (net) + Dr concession expense; Cr each fee head's income account (gross).
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function postingLines(FeeInvoice $invoice, Collection $items): array
    {
        $lines = [];

        if ($invoice->total > 0) {
            $lines[] = ['account_id' => $this->account(self::RECEIVABLE_ACCOUNT), 'debit' => $invoice->total, 'description' => $invoice->invoice_no, ...$this->party($invoice)];
        }

        $items->filter(fn (array $item): bool => $item['concession_amount'] > 0)
            ->groupBy(fn (array $item): int => $item['concession']->concessionType?->expense_account_id ?? $this->account(self::CONCESSION_ACCOUNT))
            ->each(function (Collection $group, int $accountId) use (&$lines): void {
                $lines[] = ['account_id' => $accountId, 'debit' => (int) $group->sum('concession_amount'), 'description' => 'Concession'];
            });

        $items->groupBy(fn (array $item): int => $item['head']->income_account_id)
            ->each(function (Collection $group, int $accountId) use (&$lines): void {
                $lines[] = ['account_id' => $accountId, 'credit' => (int) $group->sum('amount'), 'description' => $group->pluck('head.name')->unique()->implode(', ')];
            });

        return $lines;
    }

    private function money(int $paisa): string
    {
        return 'Rs '.number_format($paisa / 100, 2);
    }
}

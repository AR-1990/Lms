<?php

namespace App\Services;

use App\Models\AssetAssignment;
use App\Models\FeeInvoice;
use App\Models\FeeInvoiceItem;
use App\Models\FeePayment;
use App\Models\FeeRefund;
use App\Models\PaymentMethod;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;

/**
 * A student leaving school: clearance (fee dues, school items held), refunds of the
 * security deposit or unused advance, and the School Leaving Certificate.
 */
class StudentLeavingService
{
    public function __construct(
        private FeeInvoiceService $invoiceService,
        private FeePaymentService $paymentService,
        private JournalService $journalService,
        private NumberSequenceService $numberSequenceService,
        private ActivityLogger $activityLogger,
    ) {}

    /**
     * Everything that has to be settled before the certificate is issued.
     *
     * @return array<string, mixed>
     */
    public function clearance(Student $student): array
    {
        $dues = FeeInvoice::where('student_id', $student->id)
            ->whereIn('status', FeeInvoice::OPEN_STATUSES)
            ->where('balance', '>', 0)
            ->orderBy('due_date')
            ->get(['id', 'invoice_no', 'invoice_type', 'billing_month', 'due_date', 'balance']);

        $items = AssetAssignment::with('asset:id,name,asset_tag')
            ->where('assignee_type', 'student')
            ->where('assignee_id', $student->id)
            ->whereNull('returned_on')
            ->get(['id', 'asset_id', 'assigned_on', 'expected_return_on']);

        $refunds = FeeRefund::with('paymentMethod:id,name')
            ->where('student_id', $student->id)
            ->orderByDesc('refunded_on')
            ->orderByDesc('id')
            ->get(['id', 'refund_no', 'source', 'amount', 'reason', 'payment_method_id', 'reference_no', 'refunded_on', 'status', 'reversal_reason']);

        $feeDue = (int) $dues->sum('balance');
        $books = collect(app(LibraryService::class)->forBorrower('student', $student->id))->where('status', 'issued')->values()->all();

        return [
            'is_leaving' => in_array($student->current_status, Student::LEAVING_STATUSES, true),
            'is_clear' => $feeDue === 0 && $items->isEmpty() && $books === [],
            'library_books' => $books,
            'fee_due' => $feeDue,
            'dues' => $dues->map(fn (FeeInvoice $invoice): array => [
                'id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'invoice_type' => $invoice->invoice_type,
                'billing_month' => $invoice->billing_month?->format('M Y'),
                'due_date' => $invoice->due_date?->toDateString(),
                'balance' => $invoice->balance,
            ])->all(),
            'items_held' => $items->map(fn (AssetAssignment $assignment): array => [
                'id' => $assignment->id,
                'name' => $assignment->asset?->name,
                'asset_tag' => $assignment->asset?->asset_tag,
                'assigned_on' => $assignment->assigned_on?->toDateString(),
            ])->all(),
            'deposit_available' => array_sum($this->depositHeld($student)),
            'advance_available' => $this->invoiceService->advanceBalance($student->id),
            'refunds' => $refunds->map(fn (FeeRefund $refund): array => [
                ...$refund->only(['id', 'refund_no', 'source', 'amount', 'reason', 'reference_no', 'status', 'reversal_reason']),
                'refunded_on' => $refund->refunded_on->toDateString(),
                'payment_method' => $refund->paymentMethod?->name,
            ])->all(),
            'slc' => [
                'no' => $student->slc_no,
                'issued_on' => $student->slc_issued_on?->toDateString(),
                'conduct_id' => $student->slc_conduct_id,
                'remarks' => $student->slc_remarks,
            ],
        ];
    }

    /**
     * Refund register for the Fees → Refunds grid.
     *
     * @return array<string, mixed>
     */
    public function refundsDatatable(): array
    {
        $query = FeeRefund::query()
            ->with(['student:id,admission_no,first_name,middle_name,last_name', 'paymentMethod:id,name'])
            ->select(['id', 'refund_no', 'student_id', 'source', 'amount', 'reason', 'payment_method_id', 'reference_no', 'refunded_on', 'status', 'reversal_reason']);

        return DataTables::eloquent($query)
            ->addColumn('student_name', fn (FeeRefund $refund): ?string => $refund->student?->fullName())
            ->addColumn('admission_no', fn (FeeRefund $refund): ?string => $refund->student?->admission_no)
            ->addColumn('payment_method_name', fn (FeeRefund $refund): ?string => $refund->paymentMethod?->name)
            ->editColumn('refunded_on', fn (FeeRefund $refund): string => $refund->refunded_on->toDateString())
            ->filterColumn('student_name', fn ($query, string $keyword) => $query->whereHas('student', fn ($student) => $student->where('first_name', 'like', "%{$keyword}%")->orWhere('last_name', 'like', "%{$keyword}%")->orWhere('admission_no', 'like', "%{$keyword}%")))
            ->removeColumn('student', 'paymentMethod')
            ->toArray();
    }

    /**
     * Pay back the security deposit (Dr deposit liability) or unused advance (Dr Student Advances), Cr cash / bank.
     *
     * @param  array{source: string, amount: int, payment_method_id: int, refunded_on: string, reason: string, reference_no?: string|null}  $data
     *
     * @throws ValidationException when more than is held would be paid back
     */
    public function refund(Student $student, array $data, User $user): FeeRefund
    {
        $method = PaymentMethod::findOrFail($data['payment_method_id']);

        if (! $method->is_active) {
            throw ValidationException::withMessages(['payment_method_id' => ['This payment method is not active.']]);
        }

        return DB::transaction(function () use ($student, $data, $user, $method): FeeRefund {
            Student::whereKey($student->id)->lockForUpdate()->first();

            $held = $data['source'] === 'deposit'
                ? $this->depositHeld($student)
                : [$this->invoiceService->account(FeeInvoiceService::ADVANCE_ACCOUNT) => $this->invoiceService->advanceBalance($student->id)];

            if ($data['amount'] > array_sum($held)) {
                throw ValidationException::withMessages(['amount' => ['Only Rs '.number_format(array_sum($held) / 100).' is available to refund from the '.($data['source'] === 'deposit' ? 'security deposit' : 'advance').'.']]);
            }

            $party = ['party_type' => Student::class, 'party_id' => $student->id];
            $lines = [];
            $left = $data['amount'];

            foreach ($held as $accountId => $available) {
                $take = min($available, $left);

                if ($take > 0) {
                    $lines[] = ['account_id' => $accountId, 'debit' => $take, 'description' => $data['source'] === 'deposit' ? 'Security deposit refund' : 'Advance fee refund', ...$party];
                    $left -= $take;
                }
            }

            $lines[] = ['account_id' => $method->account_id, 'credit' => $data['amount'], 'description' => $method->name];

            $refund = FeeRefund::create([
                ...$data,
                'refund_no' => $this->numberSequenceService->next('refund_no'),
                'student_id' => $student->id,
                'status' => FeeRefund::STATUS_POSTED,
                'approved_by' => $user->id,
            ]);

            $entry = $this->journalService->record('PV', $data['refunded_on'], "Refund {$refund->refund_no} to {$student->fullName()}: {$data['reason']}", $lines, $refund);
            $refund->forceFill(['journal_entry_id' => $entry->id])->saveQuietly();

            $this->activityLogger->record('fees', 'refunded', "{$user->name} refunded Rs ".number_format($data['amount'] / 100)." ({$data['source']}) to {$student->fullName()} — {$refund->refund_no}", $refund);

            return $refund;
        });
    }

    /**
     * Pay the student's unpaid challans (oldest first) from the security deposit: a receipt
     * through the system "Security deposit adjustment" method, Dr Security Deposits / Cr Student Receivable.
     *
     * @throws ValidationException when there is no deposit held or nothing is due
     */
    public function adjustDeposit(Student $student, User $user): FeePayment
    {
        $clearance = $this->clearance($student);
        $amount = min($clearance['deposit_available'], $clearance['fee_due']);

        if ($amount <= 0) {
            throw ValidationException::withMessages(['deposit' => [$clearance['fee_due'] === 0 ? 'Nothing is due, so there is nothing to adjust.' : 'No security deposit is held for this student.']]);
        }

        $receipt = $this->paymentService->receive([
            'student_id' => $student->id,
            'payment_method_id' => PaymentMethod::where('code', PaymentMethod::DEPOSIT_ADJUSTMENT)->firstOrFail()->id,
            'amount' => $amount,
            'received_on' => now()->toDateString(),
            'remarks' => 'Security deposit adjusted against dues',
        ], true);

        $this->activityLogger->record('fees', 'deposit_adjusted', "{$user->name} adjusted Rs ".number_format($amount / 100)." of {$student->fullName()}'s security deposit against dues — receipt {$receipt->receipt_no}", $receipt);

        return $receipt;
    }

    /**
     * Undo a refund paid by mistake: its voucher is reversed and the money counts as held again.
     */
    public function reverseRefund(FeeRefund $refund, string $reason, User $user): FeeRefund
    {
        if ($refund->status !== FeeRefund::STATUS_POSTED) {
            throw ValidationException::withMessages(['refund' => ['This refund has already been reversed.']]);
        }

        return DB::transaction(function () use ($refund, $reason, $user): FeeRefund {
            $this->journalService->reverse($refund->journalEntry, "Refund {$refund->refund_no}: {$reason}");

            $refund->update([
                'status' => FeeRefund::STATUS_REVERSED,
                'reversal_reason' => $reason,
                'reversed_by' => $user->id,
                'reversed_at' => now(),
            ]);

            $this->activityLogger->record('fees', 'reversed', "{$user->name} reversed refund {$refund->refund_no}", $refund);

            return $refund;
        });
    }

    /**
     * Give the leaving student a certificate number (kept on re-issue) once nothing is pending.
     *
     * @param  array{issued_on: string, conduct_id?: int|null, remarks?: string|null}  $data
     *
     * @throws ValidationException while the student is still studying or has dues / school items
     */
    public function issueCertificate(Student $student, array $data): Student
    {
        if (! in_array($student->current_status, Student::LEAVING_STATUSES, true)) {
            throw ValidationException::withMessages(['slc' => ['Mark the student as withdrawn, left or graduated first.']]);
        }

        $clearance = $this->clearance($student);

        if (! $clearance['is_clear']) {
            $pending = array_filter([
                $clearance['fee_due'] > 0 ? 'Rs '.number_format($clearance['fee_due'] / 100).' of fees is still due' : null,
                $clearance['items_held'] !== [] ? count($clearance['items_held']).' school item(s) have not been returned' : null,
            ]);

            throw ValidationException::withMessages(['slc' => ['Clearance is not complete: '.implode('; ', $pending).'.']]);
        }

        return DB::transaction(function () use ($student, $data): Student {
            $isNew = $student->slc_no === null;

            $student->update([
                'slc_no' => $student->slc_no ?? $this->numberSequenceService->next('slc_no'),
                'slc_issued_on' => $data['issued_on'],
                'slc_conduct_id' => $data['conduct_id'] ?? null,
                'slc_remarks' => $data['remarks'] ?? null,
            ]);

            $this->activityLogger->record('students', $isNew ? 'slc_issued' : 'slc_updated', ($isNew ? 'Issued' : 'Updated').' School Leaving Certificate '.$student->slc_no.' for '.$student->fullName(), $student);

            return $student;
        });
    }

    public function certificatePdf(Student $student, User $user): Response
    {
        if ($student->slc_no === null) {
            throw ValidationException::withMessages(['slc' => ['Issue the certificate first.']]);
        }

        $student->load([
            'slcConduct:id,name', 'leavingReason:id,name', 'gender:id,name',
            'guardians' => fn ($query) => $query->orderByDesc('guardian_student.is_primary_contact'),
            'enrollments' => fn ($query) => $query->with(['schoolClass:id,name', 'academicSession:id,name,starts_on'])->orderByDesc('enrolled_on'),
        ]);

        $this->activityLogger->record('students', 'printed', "{$user->name} printed School Leaving Certificate {$student->slc_no}", $student);

        return Pdf::loadView('pdf.slc', [
            'student' => $student,
            'lastEnrollment' => $student->enrollments->first(),
            'firstEnrollment' => $student->enrollments->last(),
            'settings' => SystemSetting::query()->first(['school_name', 'school_tagline', 'school_address', 'school_phone']),
            'logo' => app(SystemSettingsService::class)->logoFile(),
        ])->setPaper('a4')->stream('slc-'.$student->slc_no.'.pdf');
    }

    /**
     * Refundable deposit still held for the student, per liability account: refundable heads on
     * fully paid challans, less deposit refunds already posted.
     *
     * @return array<int, int>
     */
    private function depositHeld(Student $student): array
    {
        $held = FeeInvoiceItem::query()
            ->join('fee_invoices', 'fee_invoices.id', '=', 'fee_invoice_items.fee_invoice_id')
            ->join('fee_heads', 'fee_heads.id', '=', 'fee_invoice_items.fee_head_id')
            ->where('fee_invoices.student_id', $student->id)
            ->where('fee_invoices.status', 'paid')
            ->where('fee_heads.is_refundable', true)
            ->groupBy('fee_heads.income_account_id')
            ->selectRaw('fee_heads.income_account_id as account_id, sum(fee_invoice_items.net_amount) as total')
            ->pluck('total', 'account_id')
            ->map(fn ($total): int => (int) $total)
            ->all();

        $refunded = (int) FeeRefund::where('student_id', $student->id)->where('source', 'deposit')->where('status', FeeRefund::STATUS_POSTED)->sum('amount')
            + (int) FeePayment::where('student_id', $student->id)->where('status', 'posted')
                ->whereIn('payment_method_id', PaymentMethod::where('code', PaymentMethod::DEPOSIT_ADJUSTMENT)->select('id'))->sum('amount');

        foreach ($held as $accountId => $amount) {
            $take = min($amount, $refunded);
            $held[$accountId] -= $take;
            $refunded -= $take;
        }

        return array_filter($held);
    }
}

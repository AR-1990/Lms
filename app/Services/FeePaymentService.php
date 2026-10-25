<?php

namespace App\Services;

use App\Models\AdmissionApplication;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\FeePaymentAllocation;
use App\Models\FeeRefund;
use App\Models\JournalEntry;
use App\Models\PaymentMethod;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;

/**
 * Fee receipts: money is applied to the oldest unpaid challans first; anything left over
 * is kept as the student's advance. Every receipt posts Dr Cash/Bank, Cr Student Receivable.
 */
class FeePaymentService
{
    public function __construct(
        private NumberSequenceService $numberSequenceService,
        private JournalService $journalService,
        private FeeInvoiceService $invoiceService,
        private ActivityLogger $activityLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function query(array $filters = []): Builder
    {
        return FeePayment::query()
            ->select(['id', 'receipt_no', 'student_id', 'admission_application_id', 'payment_method_id', 'amount', 'advance_amount', 'received_on', 'reference_no', 'cheque_status', 'status', 'received_by', 'created_at'])
            ->with(['student:id,admission_no,first_name,last_name', 'application:id,application_no,first_name,last_name', 'paymentMethod:id,name', 'receivedBy:id,name'])
            ->when($filters['student_id'] ?? null, fn (Builder $query, int $id) => $query->where('student_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['payment_method_id'] ?? null, fn (Builder $query, int $id) => $query->where('payment_method_id', $id))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('received_on', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('received_on', '<=', $date));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function datatable(array $filters): array
    {
        return DataTables::eloquent($this->query($filters))
            ->escapeColumns([])
            ->addColumn('payer_name', fn (FeePayment $payment): string => $payment->student?->fullName() ?? $payment->application?->fullName() ?? '—')
            ->addColumn('payer_ref', fn (FeePayment $payment): ?string => $payment->student?->admission_no ?? $payment->application?->application_no)
            ->filterColumn('payer_name', fn (Builder $query, string $keyword) => $query->where(fn (Builder $inner) => $inner
                ->where('receipt_no', 'like', "%{$keyword}%")
                ->orWhereHas('student', fn (Builder $student) => $student->whereAny(['first_name', 'last_name', 'admission_no'], 'like', "%{$keyword}%"))
                ->orWhereHas('application', fn (Builder $application) => $application->whereAny(['first_name', 'last_name', 'application_no'], 'like', "%{$keyword}%"))))
            ->addColumn('method_name', fn (FeePayment $payment): ?string => $payment->paymentMethod?->name)
            ->addColumn('received_by_name', fn (FeePayment $payment): ?string => $payment->receivedBy?->name)
            ->editColumn('received_on', fn (FeePayment $payment): string => $payment->received_on->toDateString())
            ->removeColumn('student', 'application', 'payment_method', 'received_by')
            ->toArray();
    }

    public function find(FeePayment $payment): FeePayment
    {
        return $payment->load([
            'student:id,admission_no,first_name,last_name', 'application:id,application_no,first_name,last_name', 'paymentMethod:id,name',
            'receivedBy:id,name', 'reverser:id,name', 'journalEntry:id,voucher_no', 'allocations.invoice:id,invoice_no,billing_month,invoice_type,total,balance,status',
        ]);
    }

    /**
     * A5 receipt for the counter, the parent portal and the mobile app; every print is logged.
     */
    public function pdf(FeePayment $payment, User $user): Response
    {
        $payment->load([
            'student:id,admission_no,first_name,middle_name,last_name', 'student.currentEnrollment.schoolClass:id,name', 'student.currentEnrollment.section:id,name',
            'application:id,application_no,first_name,last_name', 'paymentMethod:id,name', 'paidBy:id,name', 'receivedBy:id,name',
            'allocations.invoice:id,invoice_no,invoice_type,billing_month',
        ]);
        $this->activityLogger->record('fee_payments', 'printed', "{$user->name} printed receipt {$payment->receipt_no}", $payment);

        return Pdf::loadView('pdf.receipt', [
            'payment' => $payment,
            'settings' => SystemSetting::query()->first(['school_name', 'school_address', 'school_phone']),
            'logo' => app(SystemSettingsService::class)->logoFile(),
        ])->setPaper('a5')->stream("receipt-{$payment->receipt_no}.pdf");
    }

    /**
     * Receive money from a student (or an applicant before enrolment) and post it.
     *
     * @param  array<string, mixed>  $data
     * @param  bool  $systemMethod  an inactive system method (deposit adjustment, opening advance) the counter cannot pick
     *
     * @throws ValidationException
     */
    public function receive(array $data, bool $systemMethod = false): FeePayment
    {
        $method = PaymentMethod::findOrFail($data['payment_method_id']);

        if (! $systemMethod) {
            $this->checkMethodDetails($method, $data);
        }

        $student = isset($data['student_id']) ? Student::findOrFail($data['student_id']) : null;
        $application = $student === null ? AdmissionApplication::findOrFail($data['admission_application_id']) : null;
        $amount = (int) $data['amount'];

        return DB::transaction(function () use ($data, $method, $student, $application, $amount): FeePayment {
            $invoices = FeeInvoice::query()
                ->whereIn('status', FeeInvoice::OPEN_STATUSES)
                ->when($student, fn (Builder $query) => $query->where('student_id', $student->id))
                ->when($application, fn (Builder $query) => $query->where('admission_application_id', $application->id)->whereNull('student_id'))
                ->when(! empty($data['invoice_ids']), fn (Builder $query) => $query->whereIn('id', $data['invoice_ids']))
                ->orderBy('due_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if (! empty($data['invoice_ids']) && $invoices->count() !== count(array_unique($data['invoice_ids']))) {
                throw ValidationException::withMessages(['invoice_ids' => ['Some chosen challans are not unpaid challans of this payer.']]);
            }

            $owed = (int) $invoices->sum('balance');

            if ($application !== null && $amount > $owed) {
                throw ValidationException::withMessages(['amount' => ['An applicant can only pay what is due ('.$this->money($owed).'). Advances are taken after enrolment.']]);
            }

            $remaining = $amount;
            $allocations = [];

            foreach ($invoices as $invoice) {
                if ($remaining === 0) {
                    break;
                }

                $applied = min($remaining, $invoice->balance);
                $allocations[] = [$invoice, $applied];
                $remaining -= $applied;
            }

            $payment = FeePayment::create([
                ...collect($data)->only(['paid_by_guardian_id', 'reference_no', 'cheque_bank_id', 'cheque_date', 'remarks'])->all(),
                'receipt_no' => $this->numberSequenceService->next('receipt'),
                'student_id' => $student?->id,
                'admission_application_id' => $application?->id,
                'payment_method_id' => $method->id,
                'amount' => $amount,
                'advance_amount' => $remaining,
                'received_on' => $data['received_on'],
                'cheque_status' => $method->requires_bank_details ? 'pending' : null,
                'received_by' => auth()->id(),
                'status' => 'posted',
            ]);

            foreach ($allocations as [$invoice, $applied]) {
                FeePaymentAllocation::create(['fee_payment_id' => $payment->id, 'fee_invoice_id' => $invoice->id, 'amount' => $applied]);
                $invoice->paid_total += $applied;
                $this->invoiceService->settle($invoice);
            }

            $party = $student !== null
                ? ['party_type' => Student::class, 'party_id' => $student->id]
                : ['party_type' => AdmissionApplication::class, 'party_id' => $application->id];

            $lines = [['account_id' => $method->account_id, 'debit' => $amount, 'description' => $method->name]];

            if ($amount - $remaining > 0) {
                $lines[] = ['account_id' => $this->invoiceService->account(FeeInvoiceService::RECEIVABLE_ACCOUNT), 'credit' => $amount - $remaining, ...$party];
            }

            if ($remaining > 0) {
                $lines[] = ['account_id' => $this->invoiceService->account(FeeInvoiceService::ADVANCE_ACCOUNT), 'credit' => $remaining, 'description' => 'Advance fee', ...$party];
            }

            $payerName = $student?->fullName() ?? $application->fullName();
            $entry = $this->journalService->record('RV', $data['received_on'], "Fee receipt {$payment->receipt_no} — {$payerName}", $lines, $payment);
            $payment->forceFill(['journal_entry_id' => $entry->id])->saveQuietly();

            if ($student !== null) {
                $notifications = app(NotificationService::class);
                $notifications->notifyEvent('payment_received', $notifications->familyUserIds([$student->id])->all(), [
                    'student_name' => $student->fullName(), 'receipt_no' => $payment->receipt_no, 'amount' => $this->money($amount), 'date' => $payment->received_on->format('d M Y'),
                ], $payment, route('family.child', $student));
            }

            return $this->find($payment);
        });
    }

    /**
     * Undo a receipt (wrong entry, bounced cheque): the ledger posting is reversed and the challans owe again.
     *
     * @throws ValidationException when it was already reversed
     */
    public function reverse(FeePayment $payment, string $reason, bool $chequeBounced = false): FeePayment
    {
        if ($payment->status !== 'posted') {
            throw ValidationException::withMessages(['payment' => ['This receipt has already been reversed.']]);
        }

        // Money already paid back may have come from this receipt; reverse the refund first.
        $refund = $payment->student_id ? FeeRefund::where('student_id', $payment->student_id)->where('status', FeeRefund::STATUS_POSTED)->value('refund_no') : null;

        if ($refund !== null) {
            throw ValidationException::withMessages(['payment' => ["Refund {$refund} was paid to this student. Reverse it before reversing a receipt."]]);
        }

        return DB::transaction(function () use ($payment, $reason, $chequeBounced): FeePayment {
            $this->journalService->reverse($payment->journalEntry, "Receipt {$payment->receipt_no}: {$reason}");

            foreach ($payment->allocations()->with(['invoice', 'journalEntry'])->get() as $allocation) {
                // Advance from this receipt that already paid a later challan is taken back from it too.
                if ($allocation->from_advance && $allocation->journalEntry?->status === JournalEntry::STATUS_POSTED) {
                    $this->journalService->reverse($allocation->journalEntry, "Receipt {$payment->receipt_no} reversed: {$reason}");
                }

                $invoice = $allocation->invoice;

                // A cancelled challan already gave its advance back; it stays cancelled.
                if ($invoice->status === 'cancelled') {
                    continue;
                }

                $invoice->paid_total -= $allocation->amount;
                $this->invoiceService->settle($invoice);
            }

            $payment->forceFill([
                'status' => 'reversed',
                'reversal_reason' => $reason,
                'reversed_by' => auth()->id(),
                'reversed_at' => now(),
                'cheque_status' => $chequeBounced ? 'bounced' : $payment->cheque_status,
            ])->saveQuietly();

            $this->activityLogger->record('fee_payments', 'reversed', (auth()->user()?->name ?? 'System')." reversed receipt {$payment->receipt_no}", $payment, ['status' => ['old' => 'posted', 'new' => 'reversed'], 'reason' => ['old' => null, 'new' => $reason]]);

            return $this->find($payment);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function checkMethodDetails(PaymentMethod $method, array $data): void
    {
        if (! $method->is_active) {
            throw ValidationException::withMessages(['payment_method_id' => ['This payment method is not active.']]);
        }

        if ($method->requires_reference && blank($data['reference_no'] ?? null)) {
            throw ValidationException::withMessages(['reference_no' => ["{$method->name} needs a transaction or cheque number."]]);
        }

        if ($method->requires_bank_details && (blank($data['cheque_bank_id'] ?? null) || blank($data['cheque_date'] ?? null))) {
            throw ValidationException::withMessages(['cheque_bank_id' => ["{$method->name} needs the bank and cheque date."]]);
        }
    }

    private function money(int $paisa): string
    {
        return 'Rs '.number_format($paisa / 100, 2);
    }
}

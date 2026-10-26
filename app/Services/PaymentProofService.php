<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\FeeInvoice;
use App\Models\PaymentMethod;
use App\Models\PaymentProof;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;

/**
 * Online fee payments by screenshot: the family pays by JazzCash / EasyPaisa / bank transfer, sends the
 * screenshot with the transaction ID, and fee staff check it against their account. Approving makes a
 * normal receipt (ledger, challan, family notification); rejecting tells the family why.
 */
class PaymentProofService
{
    public function __construct(
        private AttachmentService $attachmentService,
        private FeePaymentService $feePaymentService,
        private FeeAutomationService $feeAutomationService,
        private NotificationService $notificationService,
        private ActivityLogger $activityLogger,
    ) {}

    /**
     * Where families can pay: active methods that take a transaction ID (no cheque) and have
     * instructions, plus the school's fee bank account.
     *
     * @return array{methods: array<int, array<string, mixed>>, bank: array<string, string|null>|null}
     */
    public function payTo(): array
    {
        $bank = BankAccount::with('bank:id,name')->where('is_default_for_fees', true)->first(['id', 'bank_id', 'branch', 'account_title', 'account_number', 'iban']);

        return [
            'methods' => $this->onlineMethods()->get(['id', 'name', 'payment_instructions'])->toArray(),
            'bank' => $bank === null ? null : ['bank' => $bank->bank?->name, 'branch' => $bank->branch, 'account_title' => $bank->account_title, 'account_number' => $bank->account_number, 'iban' => $bank->iban],
        ];
    }

    /**
     * The student's proofs (newest first) and where to pay.
     *
     * @return array<string, mixed>
     */
    public function forStudent(Student $student, User $user): array
    {
        $this->ensureCanSee($student, $user);

        return [
            'pay_to' => $this->payTo(),
            'proofs' => PaymentProof::where('student_id', $student->id)->with(['paymentMethod:id,name', 'invoice:id,invoice_no', 'payment:id,receipt_no'])->latest('id')->limit(50)->get()
                ->map(fn (PaymentProof $proof): array => $this->present($proof))->all(),
        ];
    }

    /**
     * @param  array{fee_invoice_id?: int|null, payment_method_id: int, amount: int, paid_on: string, reference_no: string, payer_name?: string|null, note?: string|null}  $data
     */
    public function submit(Student $student, array $data, UploadedFile $screenshot, User $user): PaymentProof
    {
        $this->ensureCanSee($student, $user);

        if (! $this->onlineMethods()->whereKey($data['payment_method_id'])->exists()) {
            throw ValidationException::withMessages(['payment_method_id' => ['Choose how you paid from the list.']]);
        }

        if (! empty($data['fee_invoice_id']) && ! FeeInvoice::whereKey($data['fee_invoice_id'])->where('student_id', $student->id)->whereIn('status', FeeInvoice::OPEN_STATUSES)->exists()) {
            throw ValidationException::withMessages(['fee_invoice_id' => ['This challan is not an unpaid challan of this student.']]);
        }

        $reference = strtoupper(trim($data['reference_no']));

        if (PaymentProof::where('payment_method_id', $data['payment_method_id'])->where('reference_no', $reference)->whereIn('status', ['pending', 'approved'])->exists()) {
            throw ValidationException::withMessages(['reference_no' => ['This transaction ID has already been sent.']]);
        }

        $proof = DB::transaction(function () use ($student, $data, $reference, $screenshot): PaymentProof {
            $proof = PaymentProof::create([...$data, 'student_id' => $student->id, 'reference_no' => $reference, 'status' => 'pending']);
            $this->attachmentService->store($proof, 'payment_proofs', $screenshot, ['title' => 'Payment screenshot '.$reference]);

            return $proof;
        });

        $this->notificationService->notify(
            $this->feeAutomationService->feeStaff(), 'payment_proof_received',
            'Online payment to check: '.$student->fullName(),
            'Rs '.number_format($proof->amount / 100).' by '.$proof->loadMissing('paymentMethod:id,name')->paymentMethod?->name.' · '.$reference,
            route('accounts.fees.online-payments'), $proof,
        );

        return $proof;
    }

    /**
     * @param  array{status?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function datatable(array $filters): array
    {
        $query = PaymentProof::query()
            ->leftJoin('students', 'students.id', '=', 'payment_proofs.student_id')
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'payment_proofs.payment_method_id')
            ->leftJoin('fee_invoices', 'fee_invoices.id', '=', 'payment_proofs.fee_invoice_id')
            ->select('payment_proofs.*', 'students.first_name', 'students.last_name', 'students.admission_no', 'payment_methods.name as method_name', 'fee_invoices.invoice_no')
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('payment_proofs.status', $status));

        return DataTables::eloquent($query)
            ->filterColumn('student_name', fn (Builder $query, string $keyword) => $query->where(fn (Builder $inner) => $inner->where('students.first_name', 'like', "%{$keyword}%")->orWhere('students.last_name', 'like', "%{$keyword}%")->orWhere('students.admission_no', 'like', "%{$keyword}%")))
            ->addColumn('student_name', fn (PaymentProof $proof): string => trim($proof->first_name.' '.$proof->last_name))
            ->toArray();
    }

    /**
     * Approve: a receipt for the amount against the chosen challan (or the oldest unpaid ones).
     */
    public function approve(PaymentProof $proof, ?string $receivedOn = null): PaymentProof
    {
        $this->ensurePending($proof);

        return DB::transaction(function () use ($proof, $receivedOn): PaymentProof {
            $payment = $this->feePaymentService->receive([
                'student_id' => $proof->student_id,
                'payment_method_id' => $proof->payment_method_id,
                'amount' => $proof->amount,
                'received_on' => $receivedOn ?? $proof->paid_on->toDateString(),
                'reference_no' => $proof->reference_no,
                'invoice_ids' => $proof->fee_invoice_id && FeeInvoice::whereKey($proof->fee_invoice_id)->whereIn('status', FeeInvoice::OPEN_STATUSES)->exists() ? [$proof->fee_invoice_id] : [],
                'remarks' => 'Online payment sent by the family'.($proof->payer_name ? ' ('.$proof->payer_name.')' : ''),
            ]);
            $proof->update(['status' => 'approved', 'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'fee_payment_id' => $payment->id]);
            $proof->screenshot?->update(['is_verified' => true, 'verified_by' => auth()->id(), 'verified_at' => now()]);
            $this->activityLogger->record('fees', 'approved', 'Approved online payment '.$proof->reference_no.' as receipt '.$payment->receipt_no, $proof);

            return $proof->load('payment:id,receipt_no');
        });
    }

    public function reject(PaymentProof $proof, string $reason): PaymentProof
    {
        $this->ensurePending($proof);

        DB::transaction(function () use ($proof, $reason): void {
            $proof->update(['status' => 'rejected', 'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'reject_reason' => $reason]);
            $this->activityLogger->record('fees', 'rejected', 'Rejected online payment '.$proof->reference_no.': '.$reason, $proof);
            $this->notificationService->notify(
                $this->notificationService->familyUserIds([$proof->student_id])->all(), 'payment_proof_rejected',
                'Online payment not accepted: '.$proof->reference_no, $reason.' Please contact the school office or send it again.',
                route('family.child', $proof->student_id), $proof,
            );
        });

        return $proof;
    }

    /**
     * The screenshot, for fee staff and the student's own family.
     */
    public function screenshot(PaymentProof $proof, User $user): Response
    {
        if (! $user->hasPermission('manage-fees')) {
            $this->ensureCanSee($proof->student, $user);
        }

        $file = $proof->screenshot ?? abort(404, 'No screenshot was attached.');

        return Storage::disk($file->disk)->response($file->path, $file->original_name);
    }

    /**
     * @return array<string, mixed>
     */
    public function present(PaymentProof $proof): array
    {
        return [
            'id' => $proof->id,
            'amount' => $proof->amount,
            'paid_on' => $proof->paid_on?->toDateString(),
            'method' => $proof->paymentMethod?->name,
            'reference_no' => $proof->reference_no,
            'invoice_no' => $proof->invoice?->invoice_no,
            'status' => $proof->status,
            'reject_reason' => $proof->reject_reason,
            'receipt_no' => $proof->payment?->receipt_no,
            'fee_payment_id' => $proof->fee_payment_id,
            'screenshot_url' => route('erp.fees.payment-proofs.screenshot', $proof->id),
            'sent_at' => $proof->created_at?->toIso8601String(),
        ];
    }

    private function onlineMethods(): Builder
    {
        return PaymentMethod::where('is_active', true)->where('requires_reference', true)->where('requires_bank_details', false)->whereNotNull('payment_instructions')->orderBy('sort_order');
    }

    private function ensurePending(PaymentProof $proof): void
    {
        if ($proof->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ['This payment has already been '.$proof->status.'.']]);
        }
    }

    private function ensureCanSee(?Student $student, User $user): void
    {
        abort_unless($student !== null && ($user->hasPermission('manage-fees') || in_array($student->id, Student::ownIdsFor($user), true)), 404, 'Student not found.');
    }
}

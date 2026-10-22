<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A fee challan. Posted to the ledger when created; afterwards only paid, waived or cancelled.
 */
#[Fillable([
    'campus_id', 'invoice_no', 'student_id', 'enrollment_id', 'academic_session_id', 'invoice_type', 'admission_application_id', 'exam_id',
    'billing_month', 'issue_date', 'due_date', 'subtotal', 'concession_total', 'fine_total', 'waived_total', 'previous_balance', 'total',
    'paid_total', 'balance', 'status', 'posted_at', 'journal_entry_id', 'fee_invoice_batch_id', 'cancelled_reason', 'cancelled_by', 'cancelled_at', 'bank_challan_no', 'notes',
])]
class FeeInvoice extends Model
{
    use LogsActivity, TracksAuthor;

    /**
     * @var array<int, string>
     */
    public const TYPES = ['registration', 'admission', 'monthly', 'exam', 'other'];

    /**
     * Statuses with money still owed or owed in the past (cancelled invoices are out).
     *
     * @var array<int, string>
     */
    public const OPEN_STATUSES = ['posted', 'partially_paid'];

    protected string $activityModule = 'fee_invoices';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cancelled_at' => 'datetime',
            'billing_month' => 'date',
            'issue_date' => 'date',
            'due_date' => 'date',
            'posted_at' => 'datetime',
            'subtotal' => 'integer',
            'concession_total' => 'integer',
            'fine_total' => 'integer',
            'waived_total' => 'integer',
            'previous_balance' => 'integer',
            'total' => 'integer',
            'paid_total' => 'integer',
            'balance' => 'integer',
        ];
    }

    public function activityLabel(): string
    {
        return $this->invoice_no;
    }

    public function items(): HasMany
    {
        return $this->hasMany(FeeInvoiceItem::class);
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplication::class, 'admission_application_id');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(FeePaymentAllocation::class);
    }

    public function waivers(): HasMany
    {
        return $this->hasMany(FeeWaiver::class);
    }

    /**
     * Who the challan is for: the student, or the applicant before enrolment.
     */
    public function payerName(): string
    {
        return $this->student?->fullName() ?? $this->application?->fullName() ?? '—';
    }
}

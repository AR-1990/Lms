<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCampus;
use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A fee receipt. Posted to the ledger when received; corrected only by reversal.
 */
#[Fillable([
    'campus_id', 'receipt_no', 'student_id', 'admission_application_id', 'paid_by_guardian_id', 'payment_method_id', 'amount', 'advance_amount',
    'received_on', 'reference_no', 'cheque_bank_id', 'cheque_date', 'cheque_status', 'received_by', 'journal_entry_id', 'status', 'reversal_reason', 'reversed_by', 'reversed_at', 'remarks',
])]
class FeePayment extends Model
{
    use BelongsToCampus, LogsActivity, TracksAuthor;

    protected string $activityModule = 'fee_payments';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'integer', 'advance_amount' => 'integer', 'received_on' => 'date', 'cheque_date' => 'date', 'reversed_at' => 'datetime'];
    }

    public function activityLabel(): string
    {
        return $this->receipt_no;
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(FeePaymentAllocation::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplication::class, 'admission_application_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'paid_by_guardian_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}

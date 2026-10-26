<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\PaymentProofFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A family's online payment (JazzCash / EasyPaisa / bank transfer) sent with its screenshot.
 * Workflow: pending → approved (a receipt is made) or rejected (with the reason).
 */
#[Fillable(['student_id', 'fee_invoice_id', 'payment_method_id', 'amount', 'paid_on', 'reference_no', 'payer_name', 'note', 'status', 'reviewed_by', 'reviewed_at', 'reject_reason', 'fee_payment_id'])]
class PaymentProof extends Model
{
    /** @use HasFactory<PaymentProofFactory> */
    use HasFactory, LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'fees';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'integer', 'paid_on' => 'date', 'reviewed_at' => 'datetime'];
    }

    public function activityLabel(): string
    {
        return 'payment proof '.$this->reference_no;
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FeeInvoice::class, 'fee_invoice_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(FeePayment::class, 'fee_payment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function screenshot(): MorphOne
    {
        return $this->morphOne(Attachment::class, 'attachable')->latestOfMany();
    }
}

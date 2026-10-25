<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Money paid back to a student's family: the refundable security deposit or unused advance fee.
 */
#[Fillable(['refund_no', 'student_id', 'fee_payment_id', 'source', 'amount', 'reason', 'payment_method_id', 'reference_no', 'refunded_on', 'journal_entry_id', 'status', 'approved_by', 'reversal_reason', 'reversed_by', 'reversed_at'])]
class FeeRefund extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    /**
     * @var array<int, string>
     */
    public const SOURCES = ['deposit', 'advance'];

    public const STATUS_POSTED = 'posted';

    public const STATUS_REVERSED = 'reversed';

    protected string $activityModule = 'fees';

    /**
     * Date used by the grid's date range.
     */
    public string $gridDateColumn = 'refunded_on';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'integer', 'refunded_on' => 'date', 'reversed_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function activityLabel(): string
    {
        return 'Refund '.$this->refund_no;
    }
}

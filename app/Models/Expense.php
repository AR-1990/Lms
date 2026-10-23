<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A bill or spend: draft → approved → paid (payment voucher), or cancelled. Paid expenses are reversed, never edited.
 */
#[Fillable(['campus_id', 'expense_no', 'expense_category_id', 'vendor_id', 'expense_date', 'amount', 'tax_amount', 'payment_method_id', 'reference_no', 'description', 'status', 'approved_by', 'approved_at', 'paid_on', 'journal_entry_id', 'cancel_reason'])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory, LogsActivity, SoftDeletes, TracksAuthor;

    /**
     * @var array<int, string>
     */
    public const STATUSES = ['draft', 'approved', 'paid', 'cancelled'];

    protected string $activityModule = 'expenses';

    /**
     * Date used by the grid's date range.
     */
    public string $gridDateColumn = 'expense_date';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['expense_date' => 'date', 'paid_on' => 'date', 'approved_at' => 'datetime', 'amount' => 'integer', 'tax_amount' => 'integer'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function total(): int
    {
        return $this->amount + $this->tax_amount;
    }
}

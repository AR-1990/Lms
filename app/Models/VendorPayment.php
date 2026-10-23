<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Money paid to a vendor against what they are owed (goods received on credit). Reversed with a reason, never edited.
 */
#[Fillable(['payment_no', 'vendor_id', 'paid_on', 'amount', 'payment_method_id', 'reference_no', 'remarks', 'status', 'journal_entry_id', 'reversal_reason'])]
class VendorPayment extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'expenses';

    /**
     * Date used by the grid's date range.
     */
    public string $gridDateColumn = 'paid_on';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['paid_on' => 'date', 'amount' => 'integer'];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}

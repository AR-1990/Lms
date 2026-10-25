<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['fee_payment_id', 'fee_invoice_id', 'amount', 'from_advance', 'journal_entry_id'])]
class FeePaymentAllocation extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'integer', 'from_advance' => 'boolean'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FeeInvoice::class, 'fee_invoice_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(FeePayment::class, 'fee_payment_id');
    }

    /**
     * Voucher that moved the money from Student Advances to the challan (advance allocations only).
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}

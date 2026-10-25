<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A late fine added to a posted challan (Dr Student Receivable / Cr the fine head income).
 */
#[Fillable(['fee_invoice_id', 'late_fee_rule_id', 'amount', 'applied_on', 'journal_entry_id'])]
class FeeInvoiceFine extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'integer', 'applied_on' => 'date'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FeeInvoice::class, 'fee_invoice_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(LateFeeRule::class, 'late_fee_rule_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}

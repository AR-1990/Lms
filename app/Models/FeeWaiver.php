<?php

namespace App\Models;

use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-off write-off of part of a challan; logged as a business action by FeeService.
 */
#[Fillable(['fee_invoice_id', 'fee_invoice_item_id', 'amount', 'reason', 'approved_by', 'journal_entry_id'])]
class FeeWaiver extends Model
{
    use TracksAuthor;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

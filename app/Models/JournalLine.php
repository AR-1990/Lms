<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One debit or credit of a voucher. Logged through its voucher, so it has no activity trait of its own.
 */
#[Fillable(['journal_entry_id', 'account_id', 'debit', 'credit', 'description', 'party_type', 'party_id', 'cost_center_id', 'line_no'])]
class JournalLine extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'debit' => 'integer',
            'credit' => 'integer',
            'line_no' => 'integer',
        ];
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}

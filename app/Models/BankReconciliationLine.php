<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['bank_reconciliation_id', 'journal_line_id', 'cleared_on'])]
class BankReconciliationLine extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['cleared_on' => 'date'];
    }

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    public function journalLine(): BelongsTo
    {
        return $this->belongsTo(JournalLine::class);
    }
}

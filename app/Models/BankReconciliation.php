<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One bank statement matched against the bank's ledger account: lines ticked as cleared until the books agree.
 */
#[Fillable(['bank_account_id', 'statement_date', 'statement_balance', 'book_balance', 'status', 'reconciled_by', 'completed_at', 'remarks'])]
class BankReconciliation extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'bank_reconciliation';

    /**
     * Date used by the grid's date range.
     */
    public string $gridDateColumn = 'statement_date';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['statement_date' => 'date', 'statement_balance' => 'integer', 'book_balance' => 'integer', 'completed_at' => 'datetime'];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function reconciler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BankReconciliationLine::class);
    }
}

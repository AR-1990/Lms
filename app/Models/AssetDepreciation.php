<?php

namespace App\Models;

use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One month of depreciation for one asset, posted in the journal voucher of that month.
 */
#[Fillable(['asset_id', 'period_month', 'amount', 'book_value_after', 'journal_entry_id'])]
class AssetDepreciation extends Model
{
    use SoftDeletes, TracksAuthor;

    /**
     * Date used by the grid's date range.
     */
    public string $gridDateColumn = 'period_month';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['period_month' => 'date', 'amount' => 'integer', 'book_value_after' => 'integer'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}

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
 * A stock count: counted vs system quantity; the difference is posted (shortage expense or excess / opening stock).
 */
#[Fillable(['adjustment_no', 'location_id', 'adjusted_on', 'reason_id', 'approved_by', 'journal_entry_id', 'remarks'])]
class StockAdjustment extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'inventory';

    /**
     * Date used by the grid's date range.
     */
    public string $gridDateColumn = 'adjusted_on';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['adjusted_on' => 'date'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'reason_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }
}

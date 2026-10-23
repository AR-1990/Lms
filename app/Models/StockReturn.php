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
 * Items given back against an issue; stock comes back at the cost it went out.
 */
#[Fillable(['stock_issue_id', 'returned_on', 'received_by', 'condition_id', 'journal_entry_id', 'remarks'])]
class StockReturn extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'inventory';

    /**
     * Date used by the grid's date range.
     */
    public string $gridDateColumn = 'returned_on';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['returned_on' => 'date'];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(StockIssue::class, 'stock_issue_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockReturnItem::class);
    }
}

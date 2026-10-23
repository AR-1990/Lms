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
 * Stock moved from one store to another (no ledger posting: same inventory account).
 */
#[Fillable(['transfer_no', 'from_location_id', 'to_location_id', 'transferred_on', 'status', 'remarks'])]
class StockTransfer extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'inventory';

    /**
     * Date used by the grid's date range.
     */
    public string $gridDateColumn = 'transferred_on';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['transferred_on' => 'date'];
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }
}

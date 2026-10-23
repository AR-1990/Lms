<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quantity and weighted average cost of one item in one store, kept in step with the stock ledger.
 */
#[Fillable(['inventory_item_id', 'location_id', 'quantity', 'average_cost'])]
class StockLevel extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'float', 'average_cost' => 'integer'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}

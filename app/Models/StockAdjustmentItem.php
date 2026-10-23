<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['stock_adjustment_id', 'inventory_item_id', 'system_qty', 'counted_qty', 'difference', 'unit_cost'])]
class StockAdjustmentItem extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['system_qty' => 'float', 'counted_qty' => 'float', 'difference' => 'float', 'unit_cost' => 'integer'];
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}

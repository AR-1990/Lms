<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['purchase_order_id', 'inventory_item_id', 'asset_category_id', 'description', 'quantity', 'unit_cost', 'line_total', 'received_quantity', 'sort_order'])]
class PurchaseOrderItem extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'float', 'unit_cost' => 'integer', 'line_total' => 'integer', 'received_quantity' => 'float'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function assetCategory(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }
}

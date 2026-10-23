<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['goods_receipt_id', 'purchase_order_item_id', 'inventory_item_id', 'asset_category_id', 'description', 'quantity', 'unit_cost', 'line_total', 'batch_no', 'expiry_date'])]
class GoodsReceiptItem extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'float', 'unit_cost' => 'integer', 'line_total' => 'integer', 'expiry_date' => 'date'];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}

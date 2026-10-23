<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['stock_issue_id', 'inventory_item_id', 'quantity', 'unit_cost', 'sale_price', 'returned_quantity', 'due_back_on'])]
class StockIssueItem extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'float', 'unit_cost' => 'integer', 'sale_price' => 'integer', 'returned_quantity' => 'float', 'due_back_on' => 'date'];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(StockIssue::class, 'stock_issue_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['stock_return_id', 'stock_issue_item_id', 'quantity', 'condition_id'])]
class StockReturnItem extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'float'];
    }

    public function stockReturn(): BelongsTo
    {
        return $this->belongsTo(StockReturn::class, 'stock_return_id');
    }

    public function issueItem(): BelongsTo
    {
        return $this->belongsTo(StockIssueItem::class, 'stock_issue_item_id');
    }
}

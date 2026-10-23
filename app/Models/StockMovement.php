<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The stock ledger: every quantity in (+) or out (−) of a store, with the document that caused it. Append-only.
 */
#[Fillable(['inventory_item_id', 'location_id', 'movement_type', 'quantity', 'unit_cost', 'balance_after', 'batch_no', 'expiry_date', 'reference_type', 'reference_id', 'moved_on', 'created_by'])]
class StockMovement extends Model
{
    /**
     * @var array<int, string>
     */
    public const TYPES = ['opening', 'purchase_in', 'issue_out', 'return_in', 'transfer_out', 'transfer_in', 'sale_out', 'adjustment'];

    /**
     * Date used by the grid's date range.
     */
    public string $gridDateColumn = 'moved_on';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'float', 'unit_cost' => 'integer', 'balance_after' => 'float', 'expiry_date' => 'date', 'moved_on' => 'date'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

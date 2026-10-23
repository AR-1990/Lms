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
 * Goods received into a store (GRN): stock goes up, asset lines become assets, and the purchase is posted to the vendor.
 */
#[Fillable(['grn_no', 'purchase_order_id', 'vendor_id', 'received_on', 'location_id', 'vendor_invoice_no', 'vendor_invoice_date', 'total', 'received_by', 'journal_entry_id', 'remarks'])]
class GoodsReceipt extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'inventory';

    /**
     * Date used by the grid's date range.
     */
    public string $gridDateColumn = 'received_on';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['received_on' => 'date', 'vendor_invoice_date' => 'date', 'total' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }
}

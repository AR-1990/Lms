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
 * An order to a vendor: draft → approved → (partially_)received, or cancelled. Lines are stock items or asset purchases.
 */
#[Fillable(['campus_id', 'po_no', 'vendor_id', 'purchase_request_id', 'location_id', 'order_date', 'expected_date', 'subtotal', 'tax', 'discount', 'total', 'status', 'approved_by', 'approved_at', 'terms', 'cancel_reason'])]
class PurchaseOrder extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    /**
     * @var array<int, string>
     */
    public const STATUSES = ['draft', 'approved', 'partially_received', 'received', 'cancelled'];

    protected string $activityModule = 'inventory';

    /**
     * Date used by the grid's date range.
     */
    public string $gridDateColumn = 'order_date';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['order_date' => 'date', 'expected_date' => 'date', 'approved_at' => 'datetime', 'subtotal' => 'integer', 'tax' => 'integer', 'discount' => 'integer', 'total' => 'integer'];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A stock item (Whiteboard Marker Blue, School Shirt size 10). Quantities are decimal; costs and prices in paisa.
 */
#[Fillable(['sku', 'item_category_id', 'name', 'unit_id', 'brand', 'size', 'color', 'description', 'reorder_level', 'reorder_quantity', 'standard_cost', 'sale_price', 'is_sellable', 'is_returnable', 'track_batches', 'barcode', 'photo_path', 'is_active'])]
class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use HasFactory, LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'inventory';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['reorder_level' => 'float', 'reorder_quantity' => 'float', 'standard_cost' => 'integer', 'sale_price' => 'integer', 'is_sellable' => 'boolean', 'is_returnable' => 'boolean', 'track_batches' => 'boolean', 'is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'unit_id');
    }

    public function levels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}

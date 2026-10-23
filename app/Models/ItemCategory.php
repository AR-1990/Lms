<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\ItemCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Stationery, uniforms, books, lab chemicals… with the ledger accounts for stock, use and sale, and the fee head a sale goes on.
 */
#[Fillable(['name', 'parent_id', 'inventory_account_id', 'expense_account_id', 'income_account_id', 'sale_fee_head_id', 'is_active'])]
class ItemCategory extends Model
{
    /** @use HasFactory<ItemCategoryFactory> */
    use HasFactory, LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'inventory';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'parent_id');
    }

    public function inventoryAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'inventory_account_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    public function saleFeeHead(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class, 'sale_fee_head_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }
}

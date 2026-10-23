<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\AssetCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Laptops, furniture, lab equipment… with the ledger accounts and the depreciation rule for assets in it.
 */
#[Fillable(['name', 'code', 'parent_id', 'asset_account_id', 'depreciation_account_id', 'accumulated_depreciation_account_id', 'depreciation_method', 'useful_life_months', 'salvage_percent', 'is_active'])]
class AssetCategory extends Model
{
    /** @use HasFactory<AssetCategoryFactory> */
    use HasFactory, LogsActivity, SoftDeletes, TracksAuthor;

    /**
     * @var array<int, string>
     */
    public const METHODS = ['none', 'straight_line', 'reducing_balance'];

    protected string $activityModule = 'assets';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['useful_life_months' => 'integer', 'salvage_percent' => 'float', 'is_active' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'parent_id');
    }

    public function assetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'asset_account_id');
    }

    public function depreciationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'depreciation_account_id');
    }

    public function accumulatedDepreciationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'accumulated_depreciation_account_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}

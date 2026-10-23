<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One tracked item (a laptop, a projector, a bus). The current holder is copied from its open assignment for fast lookup.
 */
#[Fillable(['campus_id', 'asset_tag', 'asset_category_id', 'name', 'brand', 'model', 'serial_no', 'description', 'vendor_id', 'purchase_date', 'purchase_cost', 'salvage_value', 'invoice_no', 'purchase_order_id', 'warranty_expires_on', 'location_id', 'condition_id', 'status', 'current_holder_type', 'current_holder_id', 'depreciation_start_date', 'current_book_value', 'photo_path', 'journal_entry_id'])]
class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory, LogsActivity, SoftDeletes, TracksAuthor;

    /**
     * @var array<int, string>
     */
    public const STATUSES = ['in_store', 'assigned', 'in_repair', 'lost', 'disposed'];

    /**
     * Who can hold an asset: assignee_type => model.
     *
     * @var array<string, class-string<Model>>
     */
    public const HOLDERS = ['employee' => Employee::class, 'student' => Student::class, 'department' => Department::class, 'location' => Location::class];

    protected string $activityModule = 'assets';

    /**
     * Date used by the grid's date range.
     */
    public string $gridDateColumn = 'purchase_date';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['purchase_date' => 'date', 'warranty_expires_on' => 'date', 'depreciation_start_date' => 'date', 'purchase_cost' => 'integer', 'salvage_value' => 'integer', 'current_book_value' => 'integer'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'condition_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(AssetMaintenance::class);
    }

    public function depreciations(): HasMany
    {
        return $this->hasMany(AssetDepreciation::class);
    }

    /**
     * The person, department or room holding it now (null when in store).
     */
    public function holder(): ?Model
    {
        $class = self::HOLDERS[$this->current_holder_type] ?? null;

        return $class === null || $this->current_holder_id === null ? null : $class::withTrashed()->find($this->current_holder_id);
    }

    public static function holderName(?string $type, ?int $id): ?string
    {
        $class = self::HOLDERS[$type] ?? null;
        $holder = $class === null || $id === null ? null : $class::withTrashed()->find($id);

        return match (true) {
            $holder === null => null,
            method_exists($holder, 'fullName') => $holder->fullName(),
            default => $holder->name,
        };
    }
}

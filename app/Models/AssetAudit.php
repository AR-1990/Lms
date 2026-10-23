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
 * Physical verification of the assets in a location (or everywhere): each line is ticked found / missing.
 */
#[Fillable(['audit_date', 'location_id', 'conducted_by', 'status', 'remarks', 'completed_at'])]
class AssetAudit extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'assets';

    /**
     * Date used by the grid's date range.
     */
    public string $gridDateColumn = 'audit_date';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['audit_date' => 'date', 'completed_at' => 'datetime'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function conductor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conducted_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AssetAuditItem::class);
    }
}

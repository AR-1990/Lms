<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A repair or service job. While open the asset is in_repair; completing it brings the asset back and posts the cost.
 */
#[Fillable(['asset_id', 'maintenance_type_id', 'vendor_id', 'reported_on', 'sent_on', 'completed_on', 'cost', 'description', 'status', 'previous_status', 'expense_id', 'journal_entry_id'])]
class AssetMaintenance extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    /**
     * @var array<int, string>
     */
    public const STATUSES = ['reported', 'in_progress', 'completed'];

    protected string $activityModule = 'assets';

    /**
     * Date used by the grid's date range.
     */
    public string $gridDateColumn = 'reported_on';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['reported_on' => 'date', 'sent_on' => 'date', 'completed_on' => 'date', 'cost' => 'integer'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'maintenance_type_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}

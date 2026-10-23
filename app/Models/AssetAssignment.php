<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One hand-over of an asset; returned_on is null while it is still held. Exit clearance reads open ones.
 */
#[Fillable(['asset_id', 'assignee_type', 'assignee_id', 'assigned_on', 'expected_return_on', 'returned_on', 'condition_out_id', 'condition_in_id', 'issued_by', 'received_by', 'acknowledgement_signed', 'remarks'])]
class AssetAssignment extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'assets';

    /**
     * Date used by the grid's date range.
     */
    public string $gridDateColumn = 'assigned_on';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['assigned_on' => 'date', 'expected_return_on' => 'date', 'returned_on' => 'date', 'acknowledgement_signed' => 'boolean'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function conditionOut(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'condition_out_id');
    }

    public function conditionIn(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'condition_in_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}

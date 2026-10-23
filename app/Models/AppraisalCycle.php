<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\AppraisalCycleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A review period (Annual Review 2026): draft → open (appraisals created) → closed.
 */
#[Fillable(['name', 'starts_on', 'ends_on', 'department_id', 'self_review', 'status', 'launched_at', 'closed_at'])]
class AppraisalCycle extends Model
{
    /** @use HasFactory<AppraisalCycleFactory> */
    use HasFactory, LogsActivity, SoftDeletes, TracksAuthor;

    /**
     * @var array<int, string>
     */
    public const STATUSES = ['draft', 'open', 'closed'];

    protected string $activityModule = 'appraisals';

    /**
     * Date used by the grid's date range.
     */
    public string $gridDateColumn = 'ends_on';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'self_review' => 'boolean', 'launched_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function appraisals(): HasMany
    {
        return $this->hasMany(Appraisal::class);
    }
}

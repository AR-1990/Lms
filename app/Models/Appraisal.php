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
 * One employee in one cycle: self_review → draft (reviewer scoring) → submitted → acknowledged by the employee.
 */
#[Fillable(['appraisal_cycle_id', 'employee_id', 'reviewer_id', 'status', 'overall_score', 'self_score', 'rating_id', 'strengths', 'improvements', 'goals', 'employee_comments', 'self_reviewed_at', 'submitted_at', 'acknowledged_at'])]
class Appraisal extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    /**
     * @var array<int, string>
     */
    public const STATUSES = ['self_review', 'draft', 'submitted', 'acknowledged'];

    protected string $activityModule = 'appraisals';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['overall_score' => 'float', 'self_score' => 'float', 'self_reviewed_at' => 'datetime', 'submitted_at' => 'datetime', 'acknowledged_at' => 'datetime'];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AppraisalCycle::class, 'appraisal_cycle_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewer_id');
    }

    public function rating(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'rating_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(AppraisalScore::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['appraisal_id', 'appraisal_criterion_id', 'score', 'comment', 'self_score', 'self_comment'])]
class AppraisalScore extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['score' => 'float', 'self_score' => 'float'];
    }

    public function appraisal(): BelongsTo
    {
        return $this->belongsTo(Appraisal::class);
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(AppraisalCriterion::class, 'appraisal_criterion_id');
    }
}

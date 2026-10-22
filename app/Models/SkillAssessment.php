<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['enrollment_id', 'exam_term_id', 'assessment_skill_id', 'rating_id', 'comment', 'assessed_by'])]
class SkillAssessment extends Model
{
    use LogsActivity, SoftDeletes;

    protected string $activityModule = 'performance';

    public function activityLabel(): string
    {
        return 'skill rating for enrolment #'.$this->enrollment_id;
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(AssessmentSkill::class, 'assessment_skill_id');
    }

    public function rating(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'rating_id');
    }
}

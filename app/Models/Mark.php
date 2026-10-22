<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Workflow: draft → submitted (teacher) → approved (exam cell). Changes after submission need a reason.
 */
#[Fillable(['exam_subject_id', 'enrollment_id', 'section_id', 'theory_marks', 'practical_marks', 'is_absent', 'grade', 'teacher_remarks', 'entered_by', 'status'])]
class Mark extends Model
{
    use LogsActivity, SoftDeletes;

    public const STATUSES = ['draft', 'submitted', 'approved'];

    protected string $activityModule = 'marks';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['theory_marks' => 'float', 'practical_marks' => 'float', 'is_absent' => 'boolean'];
    }

    public function activityLabel(): string
    {
        return 'marks of enrolment #'.$this->enrollment_id;
    }

    public function obtained(): float
    {
        return $this->is_absent ? 0.0 : (float) $this->theory_marks + (float) $this->practical_marks;
    }

    public function examSubject(): BelongsTo
    {
        return $this->belongsTo(ExamSubject::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}

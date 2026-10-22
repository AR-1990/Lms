<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Workflow: active → achieved | missed | cancelled.
 */
#[Fillable(['student_id', 'academic_session_id', 'subject_id', 'title', 'target_value', 'target_date', 'set_by', 'status', 'achieved_value', 'notes'])]
class StudentGoal extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    public const STATUSES = ['active', 'achieved', 'missed', 'cancelled'];

    protected string $activityModule = 'performance';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['target_date' => 'date', 'target_value' => 'float', 'achieved_value' => 'float'];
    }

    public function activityLabel(): string
    {
        return $this->title;
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}

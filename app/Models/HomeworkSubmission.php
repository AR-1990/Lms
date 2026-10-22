<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Workflow: pending → submitted | late → graded.
 */
#[Fillable(['homework_id', 'student_id', 'submitted_at', 'content', 'marks', 'feedback', 'status', 'graded_by', 'graded_at'])]
class HomeworkSubmission extends Model
{
    use LogsActivity, SoftDeletes;

    public const STATUSES = ['pending', 'submitted', 'late', 'graded'];

    protected string $activityModule = 'homework';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'graded_at' => 'datetime', 'marks' => 'float'];
    }

    public function activityLabel(): string
    {
        return 'submission #'.$this->id;
    }

    public function homework(): BelongsTo
    {
        return $this->belongsTo(Homework::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}

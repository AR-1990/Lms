<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Who teaches which subject to which section in a session.
 */
#[Fillable(['employee_id', 'academic_session_id', 'section_id', 'subject_id', 'is_class_teacher', 'periods_per_week'])]
class TeacherAssignment extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'teacher_assignments';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_class_teacher' => 'boolean', 'periods_per_week' => 'integer'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function activityLabel(): string
    {
        return ($this->subject?->name ?? 'Subject').' — '.($this->employee?->fullName() ?? 'Teacher');
    }
}

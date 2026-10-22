<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\ClassSubjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One subject taught to one class in one session (the curriculum).
 */
#[Fillable(['academic_session_id', 'class_id', 'subject_id', 'is_optional', 'periods_per_week', 'theory_max_marks', 'practical_max_marks', 'pass_marks', 'sort_order'])]
class ClassSubject extends Model
{
    /** @use HasFactory<ClassSubjectFactory> */
    use HasFactory, LogsActivity, TracksAuthor;

    use SoftDeletes;

    protected $table = 'class_subject';

    protected string $activityModule = 'curriculum';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_optional' => 'boolean',
            'periods_per_week' => 'integer',
            'theory_max_marks' => 'integer',
            'practical_max_marks' => 'integer',
            'pass_marks' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function activityLabel(): string
    {
        return trim(($this->subject?->name ?? 'Subject').' — '.($this->schoolClass?->name ?? 'Class'));
    }
}

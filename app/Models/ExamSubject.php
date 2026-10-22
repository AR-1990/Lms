<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One paper on the date sheet.
 */
#[Fillable(['exam_id', 'subject_id', 'exam_date', 'starts_at', 'duration_minutes', 'theory_max_marks', 'practical_max_marks', 'pass_marks', 'paper_setter_id', 'syllabus'])]
class ExamSubject extends Model
{
    use LogsActivity, SoftDeletes;

    protected string $activityModule = 'exams';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['exam_date' => 'date', 'theory_max_marks' => 'integer', 'practical_max_marks' => 'integer', 'pass_marks' => 'integer'];
    }

    public function activityLabel(): string
    {
        return 'paper #'.$this->id;
    }

    public function maxMarks(): int
    {
        return $this->theory_max_marks + $this->practical_max_marks;
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function marks(): HasMany
    {
        return $this->hasMany(Mark::class);
    }

    public function seatings(): HasMany
    {
        return $this->hasMany(ExamSeating::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\OnlineTestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An online MCQ test for one section and subject. Workflow: draft → published (students can attempt it
 * between opens_at and closes_at) → closed. Linked to an exam paper, each score is written into marks.
 */
#[Fillable(['title', 'instructions', 'academic_session_id', 'section_id', 'subject_id', 'exam_subject_id', 'opens_at', 'closes_at', 'duration_minutes', 'total_marks', 'shuffle_questions', 'show_answers', 'status'])]
class OnlineTest extends Model
{
    /** @use HasFactory<OnlineTestFactory> */
    use HasFactory, LogsActivity, SoftDeletes, TracksAuthor;

    public const STATUSES = ['draft', 'published', 'closed'];

    protected string $activityModule = 'exams';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['opens_at' => 'datetime', 'closes_at' => 'datetime', 'duration_minutes' => 'integer', 'total_marks' => 'integer', 'shuffle_questions' => 'boolean', 'show_answers' => 'boolean'];
    }

    public function activityLabel(): string
    {
        return 'online test '.$this->title;
    }

    /**
     * Published and inside its window: students may start or continue it.
     */
    public function isOpen(): bool
    {
        return $this->status === 'published' && ($this->opens_at === null || $this->opens_at->lte(now())) && ($this->closes_at === null || $this->closes_at->gte(now()));
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function examSubject(): BelongsTo
    {
        return $this->belongsTo(ExamSubject::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(OnlineTestQuestion::class)->orderBy('sort_order');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(OnlineTestAttempt::class);
    }
}

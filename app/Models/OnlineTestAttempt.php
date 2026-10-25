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
 * A student's one attempt at an online test: in_progress → submitted (marked automatically).
 */
#[Fillable(['online_test_id', 'student_id', 'enrollment_id', 'started_at', 'submitted_at', 'score', 'correct_count', 'status'])]
class OnlineTestAttempt extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'exams';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'submitted_at' => 'datetime', 'score' => 'integer', 'correct_count' => 'integer'];
    }

    public function activityLabel(): string
    {
        return 'online test attempt #'.$this->id;
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(OnlineTest::class, 'online_test_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(OnlineTestAnswer::class);
    }
}

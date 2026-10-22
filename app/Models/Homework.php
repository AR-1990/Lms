<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['section_id', 'subject_id', 'teacher_id', 'title', 'description', 'assigned_on', 'due_on', 'max_marks', 'allow_submission'])]
class Homework extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected $table = 'homework';

    protected string $activityModule = 'homework';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['assigned_on' => 'date', 'due_on' => 'date', 'allow_submission' => 'boolean', 'max_marks' => 'integer'];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'teacher_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(HomeworkSubmission::class);
    }
}

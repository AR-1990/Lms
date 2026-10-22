<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['diary_id', 'subject_id', 'teacher_id', 'entry_type_id', 'title', 'details', 'homework_id', 'due_on'])]
class DiaryEntry extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'diary';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['due_on' => 'date'];
    }

    public function activityLabel(): string
    {
        return $this->title ?? 'diary entry';
    }

    public function diary(): BelongsTo
    {
        return $this->belongsTo(Diary::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'teacher_id');
    }

    public function entryType(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'entry_type_id');
    }

    public function homework(): BelongsTo
    {
        return $this->belongsTo(Homework::class);
    }
}

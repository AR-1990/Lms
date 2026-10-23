<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One piece of work, on a board or personal (no board). History (status, assignees, due date) is in activity_logs.
 */
#[Fillable([
    'task_no', 'task_board_id', 'parent_task_id', 'title', 'description', 'task_status_id', 'priority_id', 'category_id', 'start_at', 'due_at',
    'completed_at', 'completed_by', 'estimated_minutes', 'spent_minutes', 'recurrence', 'recurrence_until', 'related_type', 'related_id', 'sort_order',
])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, LogsActivity, SoftDeletes, TracksAuthor;

    public const RECURRENCES = ['none', 'daily', 'weekly', 'monthly'];

    protected string $activityModule = 'tasks';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['start_at' => 'datetime', 'due_at' => 'datetime', 'completed_at' => 'datetime', 'recurrence_until' => 'date'];
    }

    public function activityLabel(): string
    {
        return $this->task_no.' '.$this->title;
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(TaskBoard::class, 'task_board_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TaskStatus::class, 'task_status_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'priority_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignees(): HasMany
    {
        return $this->hasMany(TaskAssignee::class);
    }

    public function watchers(): HasMany
    {
        return $this->hasMany(TaskWatcher::class);
    }

    public function checklist(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class)->orderBy('sort_order');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }
}

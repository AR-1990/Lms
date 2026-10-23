<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\TaskBoardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A project / board of tasks (Annual Sports Day, Admin Office…). Visibility: private (owner + members),
 * department (its employees) or everyone with the tasks permission.
 */
#[Fillable(['name', 'description', 'department_id', 'owner_id', 'visibility', 'color', 'is_archived'])]
class TaskBoard extends Model
{
    /** @use HasFactory<TaskBoardFactory> */
    use HasFactory, LogsActivity, SoftDeletes, TracksAuthor;

    public const VISIBILITIES = ['private', 'department', 'everyone'];

    protected string $activityModule = 'tasks';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_archived' => 'boolean'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(TaskBoardMember::class);
    }

    public function statuses(): HasMany
    {
        return $this->hasMany(TaskStatus::class)->orderBy('sort_order');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}

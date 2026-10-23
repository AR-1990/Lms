<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A bell reminder for one assignee before the due time.
 */
#[Fillable(['task_id', 'user_id', 'remind_at', 'sent_at'])]
class TaskReminder extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['remind_at' => 'datetime', 'sent_at' => 'datetime'];
    }
}

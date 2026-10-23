<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A Kanban column. Rows without a board are the default set used by personal tasks and copied to new boards.
 */
#[Fillable(['task_board_id', 'name', 'color', 'sort_order', 'is_done', 'is_default'])]
class TaskStatus extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_done' => 'boolean', 'is_default' => 'boolean'];
    }
}

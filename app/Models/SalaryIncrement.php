<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * History of basic-salary changes; each increment starts a new salary structure.
 */
#[Fillable(['employee_id', 'effective_from', 'old_basic', 'new_basic', 'increment_percent', 'reason', 'approved_by'])]
class SalaryIncrement extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'payroll';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['effective_from' => 'date', 'old_basic' => 'integer', 'new_basic' => 'integer', 'increment_percent' => 'float'];
    }
}

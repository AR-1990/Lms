<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Days an employee has of one leave type in a calendar year: allocated + carried forward − used.
 */
#[Fillable(['employee_id', 'leave_type_id', 'year', 'allocated', 'carried_forward', 'used'])]
class LeaveBalance extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['allocated' => 'float', 'carried_forward' => 'float', 'used' => 'float'];
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function remaining(): float
    {
        return round($this->allocated + $this->carried_forward - $this->used, 1);
    }
}

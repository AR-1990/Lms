<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Service record line: a change of status, designation or department, effective on a date.
 */
#[Fillable(['employee_id', 'field', 'old_value', 'new_value', 'effective_on', 'remarks', 'changed_by'])]
class EmployeeStatusHistory extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['effective_on' => 'date'];
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

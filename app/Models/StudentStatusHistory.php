<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['student_id', 'from_status', 'to_status', 'reason_id', 'effective_on', 'remarks', 'changed_by'])]
class StudentStatusHistory extends Model
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

    public function reason(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'reason_id');
    }
}

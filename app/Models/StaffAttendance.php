<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['employee_id', 'date', 'check_in_at', 'check_out_at', 'status', 'late_minutes', 'source', 'remarks', 'marked_by'])]
class StaffAttendance extends Model
{
    use LogsActivity;

    public const STATUSES = ['present', 'absent', 'late', 'half_day', 'leave'];

    protected string $activityModule = 'staff_attendance';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['date' => 'date', 'late_minutes' => 'integer'];
    }

    public function activityLabel(): string
    {
        return 'employee #'.$this->employee_id.' on '.$this->date?->toDateString();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

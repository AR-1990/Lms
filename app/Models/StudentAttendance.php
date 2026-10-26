<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One student, one day. Corrections update the row; every change is in the activity log.
 */
#[Fillable(['enrollment_id', 'student_id', 'section_id', 'date', 'status', 'check_in_at', 'source', 'remarks', 'marked_by'])]
class StudentAttendance extends Model
{
    use LogsActivity;

    /**
     * Statuses the percentage code counts; present, late and half day count as attended.
     */
    public const STATUSES = ['present', 'absent', 'late', 'leave', 'half_day'];

    public const ATTENDED = ['present', 'late', 'half_day'];

    protected string $activityModule = 'attendance';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function activityLabel(): string
    {
        return 'student #'.$this->student_id.' on '.$this->date?->toDateString();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}

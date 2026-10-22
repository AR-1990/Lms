<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One cell of a section's weekly timetable; a teacher can be in only one cell per day and period.
 */
#[Fillable(['section_id', 'period_id', 'day_of_week', 'subject_id', 'teacher_id', 'room', 'effective_from', 'effective_to'])]
class TimetableSlot extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'timetable';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['day_of_week' => 'integer', 'effective_from' => 'date', 'effective_to' => 'date'];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'teacher_id');
    }
}

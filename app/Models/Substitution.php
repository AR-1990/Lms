<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\SubstitutionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A teacher covering another teacher's period on one date.
 */
#[Fillable(['date', 'timetable_slot_id', 'absent_teacher_id', 'substitute_teacher_id', 'remarks'])]
class Substitution extends Model
{
    /** @use HasFactory<SubstitutionFactory> */
    use HasFactory, LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'timetable';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function activityLabel(): string
    {
        return 'cover on '.$this->date?->format('d M Y');
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(TimetableSlot::class, 'timetable_slot_id');
    }

    public function absentTeacher(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'absent_teacher_id');
    }

    public function substitute(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'substitute_teacher_id');
    }
}

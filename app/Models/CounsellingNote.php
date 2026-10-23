<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\CounsellingNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One counselling session with a student. Confidential notes are seen only by counselling staff.
 */
#[Fillable(['student_id', 'session_date', 'counsellor_id', 'concern_id', 'performance_alert_id', 'notes', 'action_plan', 'follow_up_on', 'follow_up_done_at', 'parents_met', 'is_confidential'])]
class CounsellingNote extends Model
{
    /** @use HasFactory<CounsellingNoteFactory> */
    use HasFactory, LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'counselling';

    /**
     * Date used by the grid's date range.
     */
    public string $gridDateColumn = 'session_date';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['session_date' => 'date', 'follow_up_on' => 'date', 'follow_up_done_at' => 'datetime', 'parents_met' => 'boolean', 'is_confidential' => 'boolean'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function counsellor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'counsellor_id');
    }

    public function concern(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'concern_id');
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(PerformanceAlert::class, 'performance_alert_id');
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Roll number slip; ineligible students (fee due, low attendance) get one only after an override.
 */
#[Fillable(['exam_id', 'enrollment_id', 'student_id', 'exam_roll_no', 'is_eligible', 'ineligible_reason', 'override_by', 'override_reason', 'issued_at', 'printed_count'])]
class AdmitCard extends Model
{
    use LogsActivity, SoftDeletes;

    protected string $activityModule = 'exams';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_eligible' => 'boolean', 'issued_at' => 'datetime'];
    }

    public function activityLabel(): string
    {
        return $this->exam_roll_no;
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}

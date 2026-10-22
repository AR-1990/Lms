<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A student's place for one session: class, section and roll number.
 */
#[Fillable(['student_id', 'academic_session_id', 'class_id', 'section_id', 'roll_no', 'enrolled_on', 'status', 'promoted_to_enrollment_id', 'result_status'])]
class Enrollment extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'enrollments';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['enrolled_on' => 'date'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function activityLabel(): string
    {
        return 'Enrolment of student #'.$this->student_id;
    }
}

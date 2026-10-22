<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['exam_id', 'enrollment_id', 'student_id', 'section_id', 'total_max', 'total_obtained', 'percentage', 'grade', 'position_in_section', 'position_in_class', 'attendance_percent', 'class_teacher_remarks', 'principal_remarks', 'result'])]
class ReportCard extends Model
{
    use LogsActivity, SoftDeletes;

    protected string $activityModule = 'exams';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['total_max' => 'float', 'total_obtained' => 'float', 'percentage' => 'float', 'attendance_percent' => 'float'];
    }

    public function activityLabel(): string
    {
        return 'report card #'.$this->id;
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

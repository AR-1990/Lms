<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pre-computed figures for one student and period (rebuilt, never edited by hand).
 */
#[Fillable(['enrollment_id', 'student_id', 'section_id', 'period_type', 'period_start', 'exam_term_id', 'attendance_percent', 'late_count', 'average_percent', 'class_average_percent', 'rank_in_section', 'homework_completion_percent', 'late_submissions', 'appreciation_count', 'discipline_count', 'risk_level', 'computed_at'])]
class PerformanceSnapshot extends Model
{
    public const RISK_LEVELS = ['on_track', 'watch', 'at_risk'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['period_start' => 'date', 'computed_at' => 'datetime', 'attendance_percent' => 'float', 'average_percent' => 'float', 'class_average_percent' => 'float', 'homework_completion_percent' => 'float'];
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(SubjectPerformanceSnapshot::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(ExamTerm::class, 'exam_term_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}

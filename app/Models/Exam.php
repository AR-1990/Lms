<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An exam for one class in a term. Workflow: draft → scheduled → admit_cards_issued → marks_entry → review → published.
 */
#[Fillable(['campus_id', 'exam_term_id', 'class_id', 'name', 'exam_type_id', 'grading_scale_id', 'fee_head_id', 'exam_fee_amount', 'fee_due_date', 'require_fee_clearance', 'min_attendance_percent', 'instructions', 'status', 'marks_entry_deadline', 'published_at', 'published_by'])]
class Exam extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    public const STATUSES = ['draft', 'scheduled', 'admit_cards_issued', 'marks_entry', 'review', 'published'];

    protected string $activityModule = 'exams';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['fee_due_date' => 'date', 'marks_entry_deadline' => 'date', 'published_at' => 'datetime', 'require_fee_clearance' => 'boolean', 'exam_fee_amount' => 'integer', 'min_attendance_percent' => 'float'];
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(ExamTerm::class, 'exam_term_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function examType(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'exam_type_id');
    }

    public function gradingScale(): BelongsTo
    {
        return $this->belongsTo(GradingScale::class);
    }

    public function feeHead(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(ExamSubject::class);
    }

    public function admitCards(): HasMany
    {
        return $this->hasMany(AdmitCard::class);
    }

    public function reportCards(): HasMany
    {
        return $this->hasMany(ReportCard::class);
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Exam;
use App\Services\ExamService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Exam
 */
class ExamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'exam_term_id' => $this->exam_term_id,
            'term_name' => $this->whenLoaded('term', fn (): ?string => $this->term?->name),
            'class_id' => $this->class_id,
            'class_name' => $this->whenLoaded('schoolClass', fn (): ?string => $this->schoolClass?->name),
            'exam_type_id' => $this->exam_type_id,
            'grading_scale_id' => $this->grading_scale_id,
            'fee_head_id' => $this->fee_head_id,
            'exam_fee_amount' => $this->exam_fee_amount,
            'fee_due_date' => $this->fee_due_date?->toDateString(),
            'require_fee_clearance' => $this->require_fee_clearance,
            'min_attendance_percent' => $this->min_attendance_percent,
            'instructions' => $this->instructions,
            'marks_entry_deadline' => $this->marks_entry_deadline?->toDateString(),
            'status' => $this->status,
            'next_statuses' => ExamService::TRANSITIONS[$this->status] ?? [],
            'published_at' => $this->published_at?->toIso8601String(),
            'admit_cards_count' => $this->admit_cards_count,
            'eligible_count' => $this->eligible_count,
            'papers' => $this->whenLoaded('subjects', fn (): array => $this->subjects->map(fn ($paper): array => [
                ...$paper->only(['id', 'subject_id', 'duration_minutes', 'theory_max_marks', 'practical_max_marks', 'pass_marks', 'syllabus']),
                'subject' => $paper->subject?->name,
                'exam_date' => $paper->exam_date?->toDateString(),
                'starts_at' => $paper->starts_at ? substr($paper->starts_at, 0, 5) : null,
            ])->all()),
        ];
    }
}

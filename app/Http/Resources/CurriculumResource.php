<?php

namespace App\Http\Resources;

use App\Models\ClassSubject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ClassSubject
 */
class CurriculumResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'academic_session_id' => $this->academic_session_id,
            'class_id' => $this->class_id,
            'class_name' => $this->class_name,
            'subject_id' => $this->subject_id,
            'subject_name' => $this->subject_name,
            'subject_code' => $this->subject_code,
            'is_optional' => $this->is_optional,
            'periods_per_week' => $this->periods_per_week,
            'theory_max_marks' => $this->theory_max_marks,
            'practical_max_marks' => $this->practical_max_marks,
            'total_marks' => (int) $this->theory_max_marks + (int) $this->practical_max_marks,
            'pass_marks' => $this->pass_marks,
            'sort_order' => $this->sort_order,
        ];
    }
}

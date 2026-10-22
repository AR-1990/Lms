<?php

namespace App\Http\Resources;

use App\Models\TeacherAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TeacherAssignment
 */
class TeacherAssignmentResource extends JsonResource
{
    /**
     * Expects the joined columns from TeacherAssignmentService::query().
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'teacher_name' => trim($this->first_name.' '.$this->last_name),
            'employee_code' => $this->employee_code,
            'academic_session_id' => $this->academic_session_id,
            'section_id' => $this->section_id,
            'class_name' => $this->class_name,
            'section_name' => $this->section_name,
            'subject_id' => $this->subject_id,
            'subject_name' => $this->subject_name,
            'is_class_teacher' => $this->is_class_teacher,
            'periods_per_week' => $this->periods_per_week,
        ];
    }
}

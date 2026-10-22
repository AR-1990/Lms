<?php

namespace App\Http\Resources;

use App\Models\Homework;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Homework
 */
class HomeworkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'section_id' => $this->section_id,
            'section_name' => $this->whenLoaded('section', fn (): string => trim(($this->section?->schoolClass?->name ?? '').' '.($this->section?->name ?? ''))),
            'subject_id' => $this->subject_id,
            'subject_name' => $this->whenLoaded('subject', fn (): ?string => $this->subject?->name),
            'teacher_name' => $this->whenLoaded('teacher', fn (): ?string => $this->teacher?->fullName()),
            'title' => $this->title,
            'description' => $this->description,
            'assigned_on' => $this->assigned_on?->toDateString(),
            'due_on' => $this->due_on?->toDateString(),
            'max_marks' => $this->max_marks,
            'allow_submission' => $this->allow_submission,
            'submitted_count' => $this->submitted_count,
        ];
    }
}

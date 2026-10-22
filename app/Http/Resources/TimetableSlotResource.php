<?php

namespace App\Http\Resources;

use App\Models\TimetableSlot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TimetableSlot
 */
class TimetableSlotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'section_id' => $this->section_id,
            'section' => $this->whenLoaded('section', fn (): ?string => trim(($this->section?->schoolClass?->name ?? '').' '.($this->section?->name ?? ''))),
            'period_id' => $this->period_id,
            'period' => $this->whenLoaded('period', fn (): ?array => $this->period ? ['name' => $this->period->name, 'starts_at' => $this->period->starts_at, 'ends_at' => $this->period->ends_at] : null),
            'day_of_week' => $this->day_of_week,
            'subject_id' => $this->subject_id,
            'subject' => $this->whenLoaded('subject', fn (): ?string => $this->subject?->name),
            'teacher_id' => $this->teacher_id,
            'teacher' => $this->whenLoaded('teacher', fn (): ?string => $this->teacher?->fullName()),
            'room' => $this->room,
        ];
    }
}

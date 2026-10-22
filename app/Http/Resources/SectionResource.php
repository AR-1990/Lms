<?php

namespace App\Http\Resources;

use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Section
 */
class SectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'academic_session_id' => $this->academic_session_id,
            'session_name' => $this->whenLoaded('academicSession', fn (): ?string => $this->academicSession?->name),
            'class_id' => $this->class_id,
            'class_name' => $this->class_name,
            'name' => $this->name,
            'room' => $this->room,
            'capacity' => $this->capacity,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subject
 */
class SubjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'subject_type_id' => $this->subject_type_id,
            'subject_type_name' => $this->whenLoaded('subjectType', fn (): ?string => $this->subjectType?->name),
            'has_practical' => $this->has_practical,
            'is_active' => $this->is_active,
            'curriculum_count' => $this->whenCounted('curriculum'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

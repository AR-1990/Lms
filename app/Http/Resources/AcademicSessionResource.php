<?php

namespace App\Http\Resources;

use App\Models\AcademicSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AcademicSession
 */
class AcademicSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'is_current' => $this->is_current,
            'admissions_open' => $this->admissions_open,
            'sections_count' => $this->whenCounted('sections'),
            'curriculum_count' => $this->whenCounted('curriculum'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

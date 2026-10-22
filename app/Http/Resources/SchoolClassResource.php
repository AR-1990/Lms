<?php

namespace App\Http\Resources;

use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SchoolClass
 */
class SchoolClassResource extends JsonResource
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
            'level_order' => $this->level_order,
            'wing_id' => $this->wing_id,
            'wing_name' => $this->whenLoaded('wing', fn (): ?string => $this->wing?->name),
            'next_class_id' => $this->next_class_id,
            'next_class_name' => $this->whenLoaded('nextClass', fn (): ?string => $this->nextClass?->name),
            'is_active' => $this->is_active,
            'sections_count' => $this->whenCounted('sections'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

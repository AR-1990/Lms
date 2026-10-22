<?php

namespace App\Http\Resources;

use App\Models\Designation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Designation
 */
class DesignationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'department_id' => $this->department_id,
            'department_name' => $this->whenLoaded('department', fn (): ?string => $this->department?->name),
            'grade_id' => $this->grade_id,
            'grade_name' => $this->whenLoaded('grade', fn (): ?string => $this->grade?->name),
            'is_teaching' => $this->is_teaching,
            'is_active' => $this->is_active,
            'employees_count' => $this->whenCounted('employees'),
        ];
    }
}

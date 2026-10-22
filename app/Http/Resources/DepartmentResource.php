<?php

namespace App\Http\Resources;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Department
 */
class DepartmentResource extends JsonResource
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
            'parent_id' => $this->parent_id,
            'head_employee_id' => $this->head_employee_id,
            'head_name' => $this->whenLoaded('head', fn (): ?string => $this->head?->fullName()),
            'is_active' => $this->is_active,
            'employees_count' => $this->whenCounted('employees'),
            'designations_count' => $this->whenCounted('designations'),
        ];
    }
}

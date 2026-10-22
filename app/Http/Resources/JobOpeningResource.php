<?php

namespace App\Http\Resources;

use App\Models\JobOpening;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JobOpening
 */
class JobOpeningResource extends JsonResource
{
    /**
     * Salaries are in paisa.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'department_id' => $this->department_id,
            'department_name' => $this->whenLoaded('department', fn (): ?string => $this->department?->name),
            'designation_id' => $this->designation_id,
            'designation_name' => $this->whenLoaded('designation', fn (): ?string => $this->designation?->name),
            'employment_type_id' => $this->employment_type_id,
            'positions' => $this->positions,
            'subjects_note' => $this->subjects_note,
            'min_qualification_id' => $this->min_qualification_id,
            'min_experience_years' => $this->min_experience_years,
            'salary_min' => $this->salary_min,
            'salary_max' => $this->salary_max,
            'description' => $this->description,
            'requirements' => $this->requirements,
            'opens_on' => $this->opens_on?->toDateString(),
            'closes_on' => $this->closes_on?->toDateString(),
            'is_published' => $this->is_published,
            'status' => $this->status,
            'applications_count' => $this->whenCounted('applications'),
            'hired_count' => $this->hired_count,
        ];
    }
}

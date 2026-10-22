<?php

namespace App\Http\Resources;

use App\Models\StudentLeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StudentLeaveRequest
 */
class StudentLeaveRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'student_name' => $this->whenLoaded('student', fn (): ?string => $this->student?->fullName()),
            'from_date' => $this->from_date?->toDateString(),
            'to_date' => $this->to_date?->toDateString(),
            'reason' => $this->reason,
            'status' => $this->status,
            'requested_by_name' => $this->whenLoaded('requester', fn (): ?string => $this->requester?->name),
            'reviewed_by_name' => $this->whenLoaded('reviewer', fn (): ?string => $this->reviewer?->name),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'review_remarks' => $this->review_remarks,
        ];
    }
}

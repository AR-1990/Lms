<?php

namespace App\Http\Resources;

use App\Models\AdmissionInquiry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AdmissionInquiry
 */
class AdmissionInquiryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inquiry_no' => $this->inquiry_no,
            'inquiry_date' => $this->inquiry_date?->toDateString(),
            'source_id' => $this->source_id,
            'source_name' => $this->whenLoaded('source', fn (): ?string => $this->source?->name),
            'parent_name' => $this->parent_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'child_name' => $this->child_name,
            'child_dob' => $this->child_dob?->toDateString(),
            'class_sought_id' => $this->class_sought_id,
            'class_name' => $this->whenLoaded('classSought', fn (): ?string => $this->classSought?->name),
            'academic_session_id' => $this->academic_session_id,
            'previous_school' => $this->previous_school,
            'message' => $this->message,
            'status' => $this->status,
            'follow_up_on' => $this->follow_up_on?->toDateString(),
            'assigned_to' => $this->assigned_to,
            'closed_reason_id' => $this->closed_reason_id,
            'application_id' => $this->application_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\ApprovalRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin ApprovalRequest
 */
class ApprovalRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_type' => $this->request_type,
            'module' => $this->module,
            'record_type' => Str::headline(class_basename($this->subject_type)),
            'subject_id' => $this->subject_id,
            'subject_label' => $this->subject_label,
            'reason' => $this->reason,
            'amount' => $this->amount,
            'status' => $this->status,
            'requested_by_name' => $this->requester?->name,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'decided_by_name' => $this->decider?->name,
            'decided_at' => $this->decided_at?->toIso8601String(),
            'decision_note' => $this->decision_note,
        ];
    }
}

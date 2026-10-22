<?php

namespace App\Http\Resources;

use App\Models\StudentConcession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StudentConcession
 */
class StudentConcessionResource extends JsonResource
{
    /**
     * `value` is percent × 100 or paisa.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'student_name' => $this->whenLoaded('student', fn (): ?string => $this->student?->fullName()),
            'admission_no' => $this->whenLoaded('student', fn (): ?string => $this->student?->admission_no),
            'concession_type_id' => $this->concession_type_id,
            'type_name' => $this->whenLoaded('concessionType', fn (): ?string => $this->concessionType?->name),
            'fee_head_id' => $this->fee_head_id,
            'fee_head_name' => $this->whenLoaded('feeHead', fn (): string => $this->feeHead?->name ?? 'All heads'),
            'calculation' => $this->calculation,
            'value' => $this->value,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_to' => $this->valid_to?->toDateString(),
            'reason' => $this->reason,
            'status' => $this->status,
            'approved_at' => $this->approved_at?->toIso8601String(),
        ];
    }
}

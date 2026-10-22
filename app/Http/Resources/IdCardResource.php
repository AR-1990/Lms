<?php

namespace App\Http\Resources;

use App\Models\IdCard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin IdCard
 */
class IdCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'card_no' => $this->card_no,
            'holder_type' => $this->holder_type,
            'holder_id' => $this->holder_id,
            'holder_name' => $this->holder()?->fullName(),
            'template_name' => $this->whenLoaded('template', fn (): ?string => $this->template?->name),
            'issued_on' => $this->issued_on?->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),
            'status' => $this->status,
            'status_reason' => $this->status_reason,
            'replacement_of_id' => $this->replacement_of_id,
            'print_count' => $this->print_count,
            'last_printed_at' => $this->last_printed_at?->toIso8601String(),
            'verify_url' => route('cards.verify', $this->qr_token),
        ];
    }
}

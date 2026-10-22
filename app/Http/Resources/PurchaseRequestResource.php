<?php

namespace App\Http\Resources;

use App\Models\PurchaseRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseRequest
 */
class PurchaseRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_no' => $this->request_no,
            'department_id' => $this->department_id,
            'department_name' => $this->whenLoaded('department', fn (): ?string => $this->department?->name),
            'request_date' => $this->request_date?->toDateString(),
            'needed_by' => $this->needed_by?->toDateString(),
            'purpose' => $this->purpose,
            'estimated_total' => $this->estimated_total,
            'status' => $this->status,
            'requested_by_name' => $this->whenLoaded('creator', fn (): ?string => $this->creator?->name),
            'decided_by_name' => $this->whenLoaded('approval', fn (): ?string => $this->approval?->decider?->name),
            'decided_at' => $this->whenLoaded('approval', fn (): ?string => $this->approval?->decided_at?->toIso8601String()),
            'decision_note' => $this->whenLoaded('approval', fn (): ?string => $this->approval?->decision_note),
            'items' => $this->whenLoaded('items', fn (): array => $this->items->map(fn ($item): array => [
                ...$item->only(['id', 'item_name', 'category_id', 'unit_id', 'estimated_unit_price', 'line_total', 'remarks']),
                'quantity' => (float) $item->quantity,
                'category' => $item->category?->name,
                'unit' => $item->unit?->name,
            ])->all()),
        ];
    }
}

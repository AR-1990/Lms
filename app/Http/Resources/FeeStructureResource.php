<?php

namespace App\Http\Resources;

use App\Models\FeeStructure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FeeStructure
 */
class FeeStructureResource extends JsonResource
{
    /**
     * Money is in paisa.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'academic_session_id' => $this->academic_session_id,
            'session' => $this->whenLoaded('academicSession', fn (): ?string => $this->academicSession?->name),
            'class_id' => $this->class_id,
            'class' => $this->whenLoaded('schoolClass', fn (): ?string => $this->schoolClass?->name),
            'name' => $this->name,
            'is_active' => $this->is_active,
            'items' => $this->whenLoaded('items', fn (): array => $this->items->map(fn ($item): array => [
                ...$item->only(['id', 'fee_head_id', 'charge_on', 'amount', 'due_day', 'due_in_days', 'is_mandatory', 'applies_to_new_students_only', 'sort_order']),
                'fee_head' => $item->feeHead?->name,
                'months' => $item->months->pluck('month')->sort()->values()->all(),
            ])->all()),
        ];
    }
}

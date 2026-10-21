<?php

namespace App\Http\Resources;

use App\Models\NumberSequence;
use App\Services\NumberSequenceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NumberSequence
 */
class NumberSequenceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'label' => $this->label,
            'prefix' => $this->prefix,
            'include_year' => $this->include_year,
            'padding' => $this->padding,
            'next_number' => $this->next_number,
            'reset_yearly' => $this->reset_yearly,
            'current_year' => $this->current_year,
            'preview' => app(NumberSequenceService::class)->preview($this->resource),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

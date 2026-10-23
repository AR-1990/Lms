<?php

namespace App\Http\Resources;

use App\Models\StickyNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StickyNote
 */
class StickyNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->only(['id', 'title', 'body', 'color_id', 'is_pinned', 'show_on_dashboard', 'sort_order', 'is_done']),
            'color' => $this->color?->code,
            'remind_at' => $this->remind_at?->toIso8601String(),
            'is_overdue' => $this->remind_at !== null && ! $this->is_done && $this->remind_at->isPast(),
            'done_at' => $this->done_at?->toIso8601String(),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'items' => $this->whenLoaded('items', fn (): array => $this->items->map(fn ($item): array => $item->only(['id', 'text', 'is_done']))->all()),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

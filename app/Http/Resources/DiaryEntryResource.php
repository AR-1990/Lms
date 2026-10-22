<?php

namespace App\Http\Resources;

use App\Models\DiaryEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DiaryEntry
 */
class DiaryEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'diary_id' => $this->diary_id,
            'entry_type_id' => $this->entry_type_id,
            'entry_type' => $this->whenLoaded('entryType', fn (): ?string => $this->entryType?->name),
            'subject_id' => $this->subject_id,
            'subject' => $this->whenLoaded('subject', fn (): ?string => $this->subject?->name),
            'teacher' => $this->whenLoaded('teacher', fn (): ?string => $this->teacher?->fullName()),
            'title' => $this->title,
            'details' => $this->details,
            'due_on' => $this->due_on?->toDateString(),
            'homework_id' => $this->homework_id,
        ];
    }
}

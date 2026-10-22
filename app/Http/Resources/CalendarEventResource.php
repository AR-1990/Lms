<?php

namespace App\Http\Resources;

use App\Models\CalendarEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CalendarEvent
 */
class CalendarEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'event_type_id' => $this->event_type_id,
            'event_type_name' => $this->whenLoaded('eventType', fn (): ?string => $this->eventType?->name),
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'start_time' => $this->start_time ? substr($this->start_time, 0, 5) : null,
            'end_time' => $this->end_time ? substr($this->end_time, 0, 5) : null,
            'venue' => $this->venue,
            'is_holiday' => $this->is_holiday,
            'is_public' => $this->is_public,
            'audience_role_id' => $this->audience_role_id,
            'description' => $this->description,
        ];
    }
}

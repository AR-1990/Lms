<?php

namespace App\Http\Resources;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Announcement
 */
class AnnouncementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $myRead = $this->relationLoaded('reads') ? $this->reads->first() : null;

        return [
            ...$this->only(['id', 'title', 'slug', 'category_id', 'summary', 'body', 'is_public', 'is_pinned', 'is_urgent', 'send_push', 'send_sms', 'send_email', 'requires_acknowledgement', 'status', 'views_count']),
            'publish_at' => $this->publish_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'category' => $this->whenLoaded('category', fn (): ?array => $this->category?->only(['id', 'name', 'color', 'icon'])),
            'audiences' => $this->whenLoaded('audiences', fn (): array => $this->audiences->map(fn ($audience): array => $audience->only(['audience_type', 'audience_id']))->all()),
            'published_by_name' => $this->whenLoaded('publisher', fn (): ?string => $this->publisher?->name),
            'read_at' => $this->when($this->relationLoaded('reads'), fn (): ?string => $myRead?->read_at?->toIso8601String()),
            'acknowledged_at' => $this->when($this->relationLoaded('reads'), fn (): ?string => $myRead?->acknowledged_at?->toIso8601String()),
        ];
    }
}

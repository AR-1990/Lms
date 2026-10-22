<?php

namespace App\Http\Resources;

use App\Models\WebsitePost;
use App\Models\WebsiteTestimonial;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Any website content record; posts add their category and picture URL, testimonials their initials.
 */
class WebsiteContentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->resource->attributesToArray(),
            ...($this->resource instanceof WebsitePost ? [
                'category_name' => $this->resource->category?->name,
                'image_url' => $this->resource->imageUrl(),
                'published_on' => $this->resource->published_on?->toDateString(),
            ] : []),
            ...($this->resource instanceof WebsiteTestimonial ? ['initials' => $this->resource->initials()] : []),
        ];
    }
}

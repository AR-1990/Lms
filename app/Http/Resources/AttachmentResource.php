<?php

namespace App\Http\Resources;

use App\Models\Attachment;
use App\Services\AttachmentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Number;

/**
 * @mixin Attachment
 */
class AttachmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $service = app(AttachmentService::class);

        return [
            'id' => $this->id,
            'owner' => $service->ownerKeyOf($this->resource),
            'owner_id' => $this->attachable_id,
            'document_type' => $this->documentType ? ['id' => $this->documentType->id, 'name' => $this->documentType->name] : null,
            'title' => $this->title,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'size_label' => Number::fileSize($this->size_bytes, precision: 1),
            'is_image' => str_starts_with($this->mime_type, 'image/'),
            'document_number' => $this->document_number,
            'issued_at' => $this->issued_at?->toDateString(),
            'expires_at' => $this->expires_at?->toDateString(),
            'is_expired' => $this->expires_at !== null && $this->expires_at->isPast(),
            'is_verified' => $this->is_verified,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'remarks' => $this->remarks,
            'url' => $service->temporaryUrl($this->resource),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

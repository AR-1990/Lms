<?php

namespace App\Http\Resources;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ActivityLog
 */
class ActivityLogResource extends JsonResource
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
            'user_id' => $this->user_id,
            'user_name' => $this->user_name,
            'module' => $this->module,
            'action' => $this->action,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'subject_label' => $this->subject_label,
            'description' => $this->description,
            'platform' => $this->platform,
            'ip_address' => $this->ip_address,
            'route' => $this->route,
            'created_at' => $this->created_at?->toIso8601String(),
            'changes' => $this->whenLoaded('changes', fn () => $this->changes->map(fn ($change): array => [
                'field' => $change->field,
                'old_value' => $change->old_value,
                'new_value' => $change->new_value,
            ])->all()),
        ];
    }
}

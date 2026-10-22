<?php

namespace App\Http\Resources;

use App\Models\JobInterview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JobInterview
 */
class JobInterviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'job_application_id' => $this->job_application_id,
            'round_no' => $this->round_no,
            'round_type_id' => $this->round_type_id,
            'round_type' => $this->whenLoaded('roundType', fn (): ?string => $this->roundType?->name),
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'mode' => $this->mode,
            'venue_or_link' => $this->venue_or_link,
            'status' => $this->status,
            'overall_score' => $this->overall_score,
            'recommendation' => $this->recommendation,
            'summary' => $this->summary,
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\FiscalYear;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FiscalYear
 */
class FiscalYearResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'is_current' => $this->is_current,
            'status' => $this->status,
            'closed_at' => $this->closed_at?->toIso8601String(),
            'journal_entries_count' => $this->whenCounted('journalEntries'),
        ];
    }
}

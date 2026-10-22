<?php

namespace App\Http\Resources;

use App\Models\VoucherType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VoucherType
 */
class VoucherTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'number_sequence_type' => $this->number_sequence_type,
            'is_system' => $this->is_system,
            'journal_entries_count' => $this->whenCounted('journalEntries'),
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\FeeHead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FeeHead
 */
class FeeHeadResource extends JsonResource
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
            'income_account_id' => $this->income_account_id,
            'income_account_label' => $this->whenLoaded('incomeAccount', fn (): ?string => $this->incomeAccount ? $this->incomeAccount->code.' '.$this->incomeAccount->name : null),
            'frequency_id' => $this->frequency_id,
            'frequency_name' => $this->whenLoaded('frequency', fn (): ?string => $this->frequency?->name),
            'is_refundable' => $this->is_refundable,
            'is_concession_allowed' => $this->is_concession_allowed,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}

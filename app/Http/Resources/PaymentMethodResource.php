<?php

namespace App\Http\Resources;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentMethod
 */
class PaymentMethodResource extends JsonResource
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
            'account_id' => $this->account_id,
            'account_label' => $this->whenLoaded('account', fn (): ?string => $this->account ? $this->account->code.' '.$this->account->name : null),
            'requires_reference' => $this->requires_reference,
            'requires_bank_details' => $this->requires_bank_details,
            'is_online' => $this->is_online,
            'charges_percent' => $this->charges_percent,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\ConcessionType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ConcessionType
 */
class ConcessionTypeResource extends JsonResource
{
    /**
     * `default_value` is percent × 100 or paisa.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'calculation' => $this->calculation,
            'default_value' => $this->default_value,
            'expense_account_id' => $this->expense_account_id,
            'expense_account_label' => $this->whenLoaded('expenseAccount', fn (): ?string => $this->expenseAccount ? $this->expenseAccount->code.' '.$this->expenseAccount->name : null),
            'requires_approval' => $this->requires_approval,
            'is_active' => $this->is_active,
        ];
    }
}

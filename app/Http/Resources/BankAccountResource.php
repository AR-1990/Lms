<?php

namespace App\Http\Resources;

use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BankAccount
 */
class BankAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'account_label' => $this->whenLoaded('account', fn (): ?string => $this->account ? $this->account->code.' '.$this->account->name : null),
            'bank_id' => $this->bank_id,
            'bank_name' => $this->whenLoaded('bank', fn (): ?string => $this->bank?->name),
            'branch' => $this->branch,
            'account_title' => $this->account_title,
            'account_number' => $this->account_number,
            'iban' => $this->iban,
            'branch_code' => $this->branch_code,
            'is_default_for_fees' => $this->is_default_for_fees,
        ];
    }
}

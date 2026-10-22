<?php

namespace App\Http\Resources;

use App\Models\Account;
use App\Services\AccountService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Account
 */
class AccountResource extends JsonResource
{
    /**
     * Amounts are in paisa; `balance` is signed (debit positive, credit negative).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_type_id' => $this->account_type_id,
            'account_type_name' => $this->whenLoaded('accountType', fn (): ?string => $this->accountType?->name),
            'parent_id' => $this->parent_id,
            'parent_name' => $this->whenLoaded('parent', fn (): ?string => $this->parent ? $this->parent->code.' '.$this->parent->name : null),
            'code' => $this->code,
            'name' => $this->name,
            'is_group' => $this->is_group,
            'is_bank_account' => $this->is_bank_account,
            'is_cash_account' => $this->is_cash_account,
            'is_system' => $this->is_system,
            'opening_balance' => $this->opening_balance,
            'opening_balance_type' => $this->opening_balance_type,
            'balance' => $this->is_group ? null : app(AccountService::class)->balanceOf($this->resource),
            'description' => $this->description,
            'is_active' => $this->is_active,
            'children_count' => $this->whenCounted('children'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Vendor
 */
class VendorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'contact_person' => $this->contact_person,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'ntn' => $this->ntn,
            'strn' => $this->strn,
            'payable_account_id' => $this->payable_account_id,
            'payable_account_label' => $this->whenLoaded('payableAccount', fn (): ?string => $this->payableAccount ? $this->payableAccount->code.' '.$this->payableAccount->name : null),
            'bank_name' => $this->bank_name,
            'bank_account_no' => $this->bank_account_no,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

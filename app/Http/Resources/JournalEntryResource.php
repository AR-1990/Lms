<?php

namespace App\Http\Resources;

use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JournalEntry
 */
class JournalEntryResource extends JsonResource
{
    /**
     * Amounts are in paisa.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'voucher_no' => $this->voucher_no,
            'voucher_type' => $this->whenLoaded('voucherType', fn (): ?array => $this->voucherType ? ['id' => $this->voucherType->id, 'name' => $this->voucherType->name, 'code' => $this->voucherType->code] : null),
            'fiscal_year' => $this->whenLoaded('fiscalYear', fn (): ?string => $this->fiscalYear?->name),
            'entry_date' => $this->entry_date?->toDateString(),
            'narration' => $this->narration,
            'total_debit' => $this->total_debit,
            'total_credit' => $this->total_credit,
            'status' => $this->status,
            'is_automatic' => $this->source_type !== null,
            'posted_by' => $this->whenLoaded('postedBy', fn (): ?string => $this->postedBy?->name),
            'posted_at' => $this->posted_at?->toIso8601String(),
            'reversal_reason' => $this->reversal_reason,
            'reversed_by' => $this->whenLoaded('reversedBy', fn (): ?array => $this->reversedBy ? ['id' => $this->reversedBy->id, 'voucher_no' => $this->reversedBy->voucher_no] : null),
            'reverses' => $this->whenLoaded('reverses', fn (): ?array => $this->reverses ? ['id' => $this->reverses->id, 'voucher_no' => $this->reverses->voucher_no] : null),
            'lines' => $this->whenLoaded('lines', fn (): array => $this->lines->map(fn ($line): array => [
                'id' => $line->id,
                'account_id' => $line->account_id,
                'account' => $line->account ? $line->account->code.' '.$line->account->name : null,
                'debit' => $line->debit,
                'credit' => $line->credit,
                'description' => $line->description,
            ])->all()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

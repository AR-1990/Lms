<?php

namespace App\Http\Resources;

use App\Models\FeeInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FeeInvoice
 */
class FeeInvoiceResource extends JsonResource
{
    /**
     * Money is in paisa.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_no' => $this->invoice_no,
            'invoice_type' => $this->invoice_type,
            'student_id' => $this->student_id,
            'admission_application_id' => $this->admission_application_id,
            'payer_name' => $this->payerName(),
            'payer_ref' => $this->student?->admission_no ?? $this->application?->application_no,
            'session' => $this->whenLoaded('academicSession', fn (): ?string => $this->academicSession?->name),
            'billing_month' => $this->billing_month?->format('Y-m'),
            'issue_date' => $this->issue_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'subtotal' => $this->subtotal,
            'concession_total' => $this->concession_total,
            'fine_total' => $this->fine_total,
            'waived_total' => $this->waived_total,
            'total' => $this->total,
            'paid_total' => $this->paid_total,
            'balance' => $this->balance,
            'status' => $this->status,
            'is_overdue' => in_array($this->status, FeeInvoice::OPEN_STATUSES, true) && $this->due_date?->isPast() && ! $this->due_date?->isToday(),
            'voucher_no' => $this->whenLoaded('journalEntry', fn (): ?string => $this->journalEntry?->voucher_no),
            'cancelled_reason' => $this->cancelled_reason,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancelled_by_name' => $this->whenLoaded('canceller', fn (): ?string => $this->canceller?->name),
            'notes' => $this->notes,
            'items' => $this->whenLoaded('items', fn (): array => $this->items->map(fn ($item): array => [
                ...$item->only(['id', 'fee_head_id', 'description', 'amount', 'concession_amount', 'net_amount']),
                'fee_head' => $item->feeHead?->name,
            ])->all()),
            'payments' => $this->whenLoaded('allocations', fn (): array => $this->allocations->map(fn ($allocation): array => [
                'receipt_no' => $allocation->payment?->receipt_no,
                'received_on' => $allocation->payment?->received_on?->toDateString(),
                'status' => $allocation->payment?->status,
                'amount' => $allocation->amount,
                'from_advance' => (bool) $allocation->from_advance,
            ])->all()),
            'fines' => $this->whenLoaded('fines', fn (): array => $this->fines->map(fn ($fine): array => [
                'rule' => $fine->rule?->name,
                'amount' => $fine->amount,
                'applied_on' => $fine->applied_on?->toDateString(),
            ])->all()),
            'waivers' => $this->whenLoaded('waivers', fn (): array => $this->waivers->map(fn ($waiver): array => [
                'amount' => $waiver->amount,
                'reason' => $waiver->reason,
                'approved_by' => $waiver->approvedBy?->name,
                'at' => $waiver->created_at?->toIso8601String(),
            ])->all()),
        ];
    }
}

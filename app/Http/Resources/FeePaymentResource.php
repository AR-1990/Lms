<?php

namespace App\Http\Resources;

use App\Models\FeePayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FeePayment
 */
class FeePaymentResource extends JsonResource
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
            'receipt_no' => $this->receipt_no,
            'student_id' => $this->student_id,
            'admission_application_id' => $this->admission_application_id,
            'payer_name' => $this->student?->fullName() ?? $this->application?->fullName(),
            'payer_ref' => $this->student?->admission_no ?? $this->application?->application_no,
            'payment_method' => $this->whenLoaded('paymentMethod', fn (): ?string => $this->paymentMethod?->name),
            'amount' => $this->amount,
            'advance_amount' => $this->advance_amount,
            'received_on' => $this->received_on?->toDateString(),
            'reference_no' => $this->reference_no,
            'cheque_status' => $this->cheque_status,
            'received_by' => $this->whenLoaded('receivedBy', fn (): ?string => $this->receivedBy?->name),
            'voucher_no' => $this->whenLoaded('journalEntry', fn (): ?string => $this->journalEntry?->voucher_no),
            'status' => $this->status,
            'reversal_reason' => $this->reversal_reason,
            'reversed_at' => $this->reversed_at?->toIso8601String(),
            'reversed_by_name' => $this->whenLoaded('reverser', fn (): ?string => $this->reverser?->name),
            'remarks' => $this->remarks,
            'allocations' => $this->whenLoaded('allocations', fn (): array => $this->allocations->map(fn ($allocation): array => [
                'invoice_id' => $allocation->fee_invoice_id,
                'invoice_no' => $allocation->invoice?->invoice_no,
                'invoice_type' => $allocation->invoice?->invoice_type,
                'billing_month' => $allocation->invoice?->billing_month?->format('Y-m'),
                'amount' => $allocation->amount,
            ])->all()),
        ];
    }
}

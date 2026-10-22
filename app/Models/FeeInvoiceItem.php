<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['fee_invoice_id', 'fee_head_id', 'description', 'amount', 'concession_amount', 'student_concession_id', 'net_amount'])]
class FeeInvoiceItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'integer', 'concession_amount' => 'integer', 'net_amount' => 'integer'];
    }

    public function feeHead(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class);
    }

    public function studentConcession(): BelongsTo
    {
        return $this->belongsTo(StudentConcession::class);
    }
}

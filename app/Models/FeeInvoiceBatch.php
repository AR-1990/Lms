<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['academic_session_id', 'billing_month', 'class_id', 'generated_count', 'skipped_count', 'total_amount', 'status', 'generated_by'])]
class FeeInvoiceBatch extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['billing_month' => 'date', 'generated_count' => 'integer', 'skipped_count' => 'integer', 'total_amount' => 'integer'];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}

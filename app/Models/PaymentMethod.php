<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * How money is received or paid (Cash, bank deposit, JazzCash…) and which ledger account it posts to.
 */
#[Fillable(['name', 'code', 'account_id', 'requires_reference', 'requires_bank_details', 'is_online', 'payment_instructions', 'charges_percent', 'is_active', 'sort_order'])]
class PaymentMethod extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    /**
     * System method behind "Adjust deposit against dues" (Dr Security Deposits instead of cash).
     */
    public const DEPOSIT_ADJUSTMENT = 'DEPOSIT';

    /**
     * System method behind imported opening advances (Dr Opening Balance Equity, Cr Student Advances).
     */
    public const OPENING_ADVANCE = 'OPENING';

    protected string $activityModule = 'payment_methods';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requires_reference' => 'boolean',
            'requires_bank_details' => 'boolean',
            'is_online' => 'boolean',
            'charges_percent' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}

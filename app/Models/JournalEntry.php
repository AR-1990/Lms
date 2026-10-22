<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A voucher. Drafts can change; posted and reversed vouchers are never edited or deleted.
 */
#[Fillable(['campus_id', 'voucher_no', 'voucher_type_id', 'fiscal_year_id', 'entry_date', 'narration', 'source_type', 'source_id', 'total_debit', 'total_credit', 'status', 'posted_by', 'posted_at', 'reversed_by_entry_id', 'reverses_entry_id', 'reversal_reason', 'reversed_by_user_id', 'reversed_at'])]
class JournalEntry extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_POSTED = 'posted';

    public const STATUS_REVERSED = 'reversed';

    /**
     * Statuses whose lines count in balances (a reversed voucher stays, offset by its reversal).
     *
     * @var array<int, string>
     */
    public const LEDGER_STATUSES = [self::STATUS_POSTED, self::STATUS_REVERSED];

    protected string $activityModule = 'vouchers';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'total_debit' => 'integer',
            'total_credit' => 'integer',
            'posted_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('line_no');
    }

    public function voucherType(): BelongsTo
    {
        return $this->belongsTo(VoucherType::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /**
     * The user who reversed this voucher (reversedBy is the reversing voucher).
     */
    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_user_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_entry_id');
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_entry_id');
    }

    public function activityLabel(): string
    {
        return $this->voucher_no;
    }
}

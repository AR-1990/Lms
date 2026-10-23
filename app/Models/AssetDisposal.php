<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Selling, scrapping, donating or writing off an asset; posts the gain or loss against its book value.
 */
#[Fillable(['asset_id', 'disposed_on', 'method', 'sale_amount', 'payment_method_id', 'book_value_at_disposal', 'reason', 'approved_by', 'journal_entry_id'])]
class AssetDisposal extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    /**
     * @var array<int, string>
     */
    public const METHODS = ['sold', 'scrapped', 'donated', 'lost'];

    protected string $activityModule = 'assets';

    /**
     * Date used by the grid's date range.
     */
    public string $gridDateColumn = 'disposed_on';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['disposed_on' => 'date', 'sale_amount' => 'integer', 'book_value_at_disposal' => 'integer'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}

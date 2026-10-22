<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A kind of fee (Tuition, Admission, Lab…) and the income account it is credited to.
 */
#[Fillable(['name', 'code', 'income_account_id', 'frequency_id', 'is_refundable', 'is_concession_allowed', 'sort_order', 'is_active'])]
class FeeHead extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'fee_heads';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_refundable' => 'boolean', 'is_concession_allowed' => 'boolean', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function incomeAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'income_account_id');
    }

    public function frequency(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'frequency_id');
    }
}

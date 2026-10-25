<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\LateFeeRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A late payment fine for overdue challans: fixed once, per day late, or a percent of what is still owed.
 */
#[Fillable(['name', 'grace_days', 'calculation', 'amount', 'max_amount', 'fee_head_id', 'invoice_type', 'is_active'])]
class LateFeeRule extends Model
{
    /** @use HasFactory<LateFeeRuleFactory> */
    use HasFactory, LogsActivity, SoftDeletes, TracksAuthor;

    /**
     * @var array<int, string>
     */
    public const CALCULATIONS = ['fixed', 'per_day', 'percent'];

    protected string $activityModule = 'fees';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['grace_days' => 'integer', 'amount' => 'integer', 'max_amount' => 'integer', 'is_active' => 'boolean'];
    }

    public function feeHead(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class);
    }

    public function fines(): HasMany
    {
        return $this->hasMany(FeeInvoiceFine::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Chart of accounts node. Group accounts are headers; only non-group accounts take journal lines.
 */
#[Fillable(['campus_id', 'account_type_id', 'parent_id', 'code', 'name', 'is_group', 'is_bank_account', 'is_cash_account', 'is_system', 'opening_balance', 'opening_balance_type', 'description', 'is_active'])]
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory, LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'accounts';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_group' => 'boolean',
            'is_bank_account' => 'boolean',
            'is_cash_account' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'opening_balance' => 'integer',
        ];
    }

    public function accountType(): BelongsTo
    {
        return $this->belongsTo(AccountType::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function activityLabel(): string
    {
        return $this->code.' '.$this->name;
    }
}

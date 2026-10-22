<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['account_id', 'bank_id', 'branch', 'account_title', 'account_number', 'iban', 'branch_code', 'is_default_for_fees'])]
class BankAccount extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'bank_accounts';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default_for_fees' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'bank_id');
    }

    public function activityLabel(): string
    {
        return $this->account_title.' ('.$this->account_number.')';
    }
}

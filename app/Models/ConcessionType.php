<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Sibling, Merit, Staff Child… `default_value` is percent × 100 (25% = 2500) or paisa for fixed.
 */
#[Fillable(['name', 'calculation', 'default_value', 'expense_account_id', 'requires_approval', 'is_active'])]
class ConcessionType extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'concession_types';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['default_value' => 'integer', 'requires_approval' => 'boolean', 'is_active' => 'boolean'];
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }
}

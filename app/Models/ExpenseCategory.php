<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\ExpenseCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Utilities, rent, repairs, transport fuel… each posted to its own expense account.
 */
#[Fillable(['name', 'parent_id', 'expense_account_id', 'is_active'])]
class ExpenseCategory extends Model
{
    /** @use HasFactory<ExpenseCategoryFactory> */
    use HasFactory, LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'expenses';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'parent_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}

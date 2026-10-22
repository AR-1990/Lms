<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The five fixed account classes (Asset, Liability, Equity, Income, Expense); seeded, never edited.
 */
#[Fillable(['name', 'normal_balance'])]
class AccountType extends Model
{
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }
}

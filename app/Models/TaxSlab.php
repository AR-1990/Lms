<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Yearly income-tax slab: tax = fixed_tax + (annual taxable income − min_income) × rate_percent.
 */
#[Fillable(['fiscal_year_id', 'min_income', 'max_income', 'fixed_tax', 'rate_percent'])]
class TaxSlab extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'payroll';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['min_income' => 'integer', 'max_income' => 'integer', 'fixed_tax' => 'integer', 'rate_percent' => 'float'];
    }
}

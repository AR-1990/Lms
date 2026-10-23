<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\SalaryComponentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An earning or deduction on payslips (House Rent, EOBI…). `percent_of_basic` stores percent × 100 in default_value.
 * System codes the payroll fills itself: BASIC, ABSENT (absent / unpaid leave), LOAN (instalments), TAX (tax slabs).
 */
#[Fillable(['name', 'code', 'type', 'calculation', 'default_value', 'is_taxable', 'account_id', 'is_system', 'sort_order', 'is_active'])]
class SalaryComponent extends Model
{
    /** @use HasFactory<SalaryComponentFactory> */
    use HasFactory, LogsActivity, SoftDeletes, TracksAuthor;

    public const TYPES = ['earning', 'deduction'];

    public const CALCULATIONS = ['fixed', 'percent_of_basic'];

    public const SYSTEM_CODES = ['BASIC', 'ABSENT', 'LATE', 'LOAN', 'TAX'];

    protected string $activityModule = 'payroll';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['default_value' => 'integer', 'is_taxable' => 'boolean', 'is_system' => 'boolean', 'is_active' => 'boolean'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An employee's pay from a date: basic salary plus fixed allowances and deductions. A new structure ends the previous one.
 */
#[Fillable(['employee_id', 'effective_from', 'effective_to', 'basic_salary', 'gross_salary', 'remarks'])]
class EmployeeSalaryStructure extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'payroll';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date', 'basic_salary' => 'integer', 'gross_salary' => 'integer'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(EmployeeSalaryStructureItem::class);
    }
}

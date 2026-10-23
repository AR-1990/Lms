<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One monthly instalment of a loan: due → deducted (by a posted payroll) or skipped.
 */
#[Fillable(['employee_loan_id', 'due_month', 'amount', 'payslip_id', 'status'])]
class EmployeeLoanInstalment extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['due_month' => 'date', 'amount' => 'integer'];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(EmployeeLoan::class, 'employee_loan_id');
    }
}

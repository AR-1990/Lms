<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One employee's pay for a payroll month, with its earning and deduction lines.
 */
#[Fillable(['payroll_run_id', 'employee_id', 'working_days', 'present_days', 'leave_days', 'unpaid_leave_days', 'absent_days', 'late_count', 'late_minutes', 'late_deduction_days', 'gross', 'deductions', 'net', 'payment_method_id', 'paid_on', 'bank_reference', 'status'])]
class Payslip extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['working_days' => 'float', 'present_days' => 'float', 'leave_days' => 'float', 'unpaid_leave_days' => 'float', 'absent_days' => 'float', 'late_count' => 'integer', 'late_minutes' => 'integer', 'late_deduction_days' => 'float', 'gross' => 'integer', 'deductions' => 'integer', 'net' => 'integer', 'paid_on' => 'date'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayslipItem::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(PayslipLeave::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(PayrollAdjustment::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}

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
 * One month's salaries. Workflow: draft (regenerate freely) → approved → posted (salary journal) → paid (payment voucher).
 */
#[Fillable(['month', 'fiscal_year_id', 'status', 'total_gross', 'total_deductions', 'total_net', 'employee_count', 'approved_by', 'approved_at', 'posted_at', 'journal_entry_id', 'payment_journal_entry_id', 'paid_at'])]
class PayrollRun extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    public const STATUSES = ['draft', 'approved', 'posted', 'paid'];

    protected string $activityModule = 'payroll';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['month' => 'date', 'total_gross' => 'integer', 'total_deductions' => 'integer', 'total_net' => 'integer', 'approved_at' => 'datetime', 'posted_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function activityLabel(): string
    {
        return 'payroll '.$this->month?->format('M Y');
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

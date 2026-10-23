<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\EmployeeLoanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A staff loan or salary advance. Workflow: pending → approved (admin) → active (paid out) → closed; or rejected.
 * Instalments are deducted through payroll.
 */
#[Fillable(['employee_id', 'loan_type_id', 'amount', 'issued_on', 'instalments', 'instalment_amount', 'start_month', 'outstanding', 'status', 'reason', 'approved_by', 'approved_at', 'review_remarks', 'payment_method_id', 'journal_entry_id'])]
class EmployeeLoan extends Model
{
    /** @use HasFactory<EmployeeLoanFactory> */
    use HasFactory, LogsActivity, SoftDeletes, TracksAuthor;

    public const STATUSES = ['pending', 'approved', 'active', 'closed', 'rejected'];

    protected string $activityModule = 'payroll';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'integer', 'issued_on' => 'date', 'instalment_amount' => 'integer', 'start_month' => 'date', 'outstanding' => 'integer', 'approved_at' => 'datetime'];
    }

    public function activityLabel(): string
    {
        return 'loan #'.$this->id;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function loanType(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'loan_type_id');
    }

    public function instalmentRows(): HasMany
    {
        return $this->hasMany(EmployeeLoanInstalment::class);
    }
}

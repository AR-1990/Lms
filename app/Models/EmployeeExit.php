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
 * A member of staff leaving: clearance by each area, then one final settlement paid as a voucher.
 */
#[Fillable(['employee_id', 'exit_type', 'reason_id', 'notice_date', 'last_working_day', 'status', 'final_settlement_amount', 'settlement_paid_on', 'payment_method_id', 'journal_entry_id', 'exit_interview_notes', 'remarks'])]
class EmployeeExit extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    /**
     * Exit type => the employee status it ends in.
     *
     * @var array<string, string>
     */
    public const TYPES = ['resignation' => 'resigned', 'termination' => 'terminated', 'retirement' => 'retired', 'contract_end' => 'resigned'];

    public const STATUS_CLEARANCE = 'clearance';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_CANCELLED = 'cancelled';

    protected string $activityModule = 'hr';

    /**
     * Date used by the grid's date range.
     */
    public string $gridDateColumn = 'last_working_day';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['notice_date' => 'date', 'last_working_day' => 'date', 'settlement_paid_on' => 'date', 'final_settlement_amount' => 'integer'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'reason_id');
    }

    public function clearances(): HasMany
    {
        return $this->hasMany(ExitClearance::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ExitSettlementLine::class)->orderBy('sort_order');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function activityLabel(): string
    {
        return 'Exit of employee #'.$this->employee_id;
    }
}

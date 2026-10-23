<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\LeaveRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A staff leave application. Workflow: pending → approved | rejected; pending or future approved leave can be cancelled.
 */
#[Fillable(['employee_id', 'leave_type_id', 'from_date', 'to_date', 'days', 'is_half_day', 'reason', 'substitute_employee_id', 'status', 'approved_by', 'approved_at', 'review_remarks'])]
class LeaveRequest extends Model
{
    /** @use HasFactory<LeaveRequestFactory> */
    use HasFactory, LogsActivity, SoftDeletes, TracksAuthor;

    public const STATUSES = ['pending', 'approved', 'rejected', 'cancelled'];

    protected string $activityModule = 'leave';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['from_date' => 'date', 'to_date' => 'date', 'days' => 'float', 'is_half_day' => 'boolean', 'approved_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function substitute(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'substitute_employee_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function activityLabel(): string
    {
        return 'leave '.$this->from_date?->format('d M').'–'.$this->to_date?->format('d M Y');
    }
}

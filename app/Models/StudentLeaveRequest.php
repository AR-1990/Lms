<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['student_id', 'requested_by', 'from_date', 'to_date', 'reason', 'status', 'reviewed_by', 'reviewed_at', 'review_remarks'])]
class StudentLeaveRequest extends Model
{
    use LogsActivity, SoftDeletes;

    public const STATUSES = ['pending', 'approved', 'rejected'];

    protected string $activityModule = 'attendance';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['from_date' => 'date', 'to_date' => 'date', 'reviewed_at' => 'datetime'];
    }

    public function activityLabel(): string
    {
        return 'leave '.$this->from_date?->toDateString().' – '.$this->to_date?->toDateString();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

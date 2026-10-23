<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\LeaveTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Casual, Sick, Annual, Unpaid… (managed by the school). Unpaid types have no balance limit.
 */
#[Fillable(['name', 'code', 'days_per_year', 'is_paid', 'carry_forward', 'max_carry_forward', 'requires_document', 'applicable_gender_id', 'sort_order', 'is_active'])]
class LeaveType extends Model
{
    /** @use HasFactory<LeaveTypeFactory> */
    use HasFactory, LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'leave';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['days_per_year' => 'float', 'is_paid' => 'boolean', 'carry_forward' => 'boolean', 'max_carry_forward' => 'float', 'requires_document' => 'boolean', 'is_active' => 'boolean'];
    }

    public function gender(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'applicable_gender_id');
    }
}

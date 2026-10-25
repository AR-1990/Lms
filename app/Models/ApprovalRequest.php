<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCampus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A deletion or purchase waiting for (or decided by) an administrator. Approved deletions
 * form the register of who asked to delete what, why, and who approved it.
 */
#[Fillable(['campus_id', 'request_type', 'module', 'subject_type', 'subject_id', 'subject_label', 'reason', 'amount', 'status', 'requested_by', 'requested_at', 'decided_by', 'decided_at', 'decision_note'])]
class ApprovalRequest extends Model
{
    use BelongsToCampus, SoftDeletes;

    public const TYPES = ['delete', 'purchase'];

    public const STATUSES = ['pending', 'approved', 'rejected'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'decided_at' => 'datetime'];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}

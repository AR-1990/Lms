<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCampus;
use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['campus_id', 'request_no', 'department_id', 'request_date', 'needed_by', 'purpose', 'estimated_total', 'status', 'approval_request_id'])]
class PurchaseRequest extends Model
{
    use BelongsToCampus, LogsActivity, SoftDeletes, TracksAuthor;

    /**
     * Workflow: pending → approved | rejected. Only a pending request can be edited.
     */
    public const STATUSES = ['pending', 'approved', 'rejected'];

    protected string $activityModule = 'purchase_requests';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['request_date' => 'date', 'needed_by' => 'date'];
    }

    public function activityLabel(): string
    {
        return $this->request_no;
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class)->orderBy('sort_order');
    }

    public function approval(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'user_name', 'module', 'action', 'subject_type', 'subject_id', 'subject_label',
    'description', 'batch_uuid', 'platform', 'ip_address', 'user_agent', 'route', 'created_at',
])]
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * Field-level before/after values for this log entry.
     */
    public function changes(): HasMany
    {
        return $this->hasMany(ActivityLogChange::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

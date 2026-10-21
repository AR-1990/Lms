<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Any uploaded document or image, owned by any record (user, student, employee, asset…).
 */
#[Fillable([
    'attachable_type', 'attachable_id', 'document_type_id', 'title', 'disk', 'path', 'original_name',
    'mime_type', 'size_bytes', 'document_number', 'issued_at', 'expires_at', 'is_verified',
    'verified_by', 'verified_at', 'remarks', 'created_by', 'updated_by',
])]
class Attachment extends Model
{
    use LogsActivity, SoftDeletes;

    protected string $activityModule = 'attachments';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'issued_at' => 'date',
            'expires_at' => 'date',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'document_type_id');
    }

    public function activityLabel(): string
    {
        return $this->title ?: $this->original_name;
    }
}

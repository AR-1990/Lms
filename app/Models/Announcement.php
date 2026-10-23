<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A notice for any mix of audiences. Public ones also show on the website without login.
 * Workflow: draft → scheduled (publish_at in the future) → published → archived.
 */
#[Fillable([
    'title', 'slug', 'category_id', 'summary', 'body', 'cover_image_path', 'is_public', 'is_pinned', 'is_urgent', 'publish_at', 'expires_at',
    'send_push', 'send_sms', 'send_email', 'requires_acknowledgement', 'status', 'published_by', 'views_count',
])]
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory, LogsActivity, SoftDeletes, TracksAuthor;

    public const STATUSES = ['draft', 'scheduled', 'published', 'archived'];

    /**
     * Who an announcement can be for; the `all_*` and `everyone` types carry no audience_id.
     */
    public const AUDIENCE_TYPES = ['everyone', 'all_students', 'all_parents', 'all_employees', 'role', 'class', 'section', 'department', 'user'];

    protected string $activityModule = 'announcements';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'publish_at' => 'datetime', 'expires_at' => 'datetime', 'is_public' => 'boolean', 'is_pinned' => 'boolean', 'is_urgent' => 'boolean',
            'send_push' => 'boolean', 'send_sms' => 'boolean', 'send_email' => 'boolean', 'requires_acknowledgement' => 'boolean',
        ];
    }

    public function activityLabel(): string
    {
        return $this->title;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AnnouncementCategory::class, 'category_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function audiences(): HasMany
    {
        return $this->hasMany(AnnouncementAudience::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    /**
     * Published, already live and not expired.
     */
    public function scopeLive(Builder $query): void
    {
        $query->where('status', 'published')
            ->where(fn (Builder $inner) => $inner->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
            ->where(fn (Builder $inner) => $inner->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}

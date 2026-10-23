<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\StickyNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A private reminder on its owner's dashboard; nobody else can ever see it.
 */
#[Fillable(['user_id', 'title', 'body', 'color_id', 'is_pinned', 'show_on_dashboard', 'sort_order', 'remind_at', 'reminded_at', 'is_done', 'done_at', 'archived_at', 'related_type', 'related_id'])]
class StickyNote extends Model
{
    /** @use HasFactory<StickyNoteFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected string $activityModule = 'sticky_notes';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean', 'show_on_dashboard' => 'boolean', 'is_done' => 'boolean',
            'remind_at' => 'datetime', 'reminded_at' => 'datetime', 'done_at' => 'datetime', 'archived_at' => 'datetime',
        ];
    }

    public function activityLabel(): string
    {
        return $this->title ?: 'sticky note';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'color_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StickyNoteItem::class)->orderBy('sort_order');
    }
}

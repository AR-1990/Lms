<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use App\Services\WebsitePageService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A content block of a website page (principal's message, facilities, admission steps…).
 */
#[Fillable(['website_page_id', 'section_key', 'subtitle', 'title', 'description', 'body', 'badge', 'image_path', 'image_caption', 'image_note', 'cta_label', 'cta_url', 'cta2_label', 'cta2_url', 'sort_order', 'is_active'])]
class WebsiteSection extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'website';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function activityLabel(): string
    {
        return ($this->title ?? $this->section_key).' block';
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(WebsitePage::class, 'website_page_id');
    }

    /**
     * Top-level cards, in order, each with its child items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(WebsiteSectionItem::class)->whereNull('parent_id')->orderBy('sort_order');
    }

    public function imageUrl(): ?string
    {
        return WebsitePage::imageUrl($this->image_path);
    }

    /**
     * Any change reaches the public site at once (the pages are cached, see WebsitePageService).
     */
    protected static function booted(): void
    {
        static::saved(fn () => WebsitePageService::flush());
        static::deleted(fn () => WebsitePageService::flush());
    }
}

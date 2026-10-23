<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A card inside a website block (a facility, a step, a value…). Child items are its bullet points or rows.
 */
#[Fillable(['website_section_id', 'parent_id', 'title', 'subtitle', 'body', 'icon', 'tone', 'badge', 'value', 'image_path', 'link_label', 'link_url', 'link2_label', 'link2_url', 'sort_order', 'is_active'])]
class WebsiteSectionItem extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    /**
     * Colour names the page design understands (card icons and badges).
     */
    public const TONES = ['orange', 'blue', 'emerald', 'amber', 'violet', 'red', 'dark'];

    protected string $activityModule = 'website';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function children(): HasMany
    {
        return $this->hasMany(WebsiteSectionItem::class, 'parent_id')->orderBy('sort_order');
    }

    public function imageUrl(): ?string
    {
        return WebsitePage::imageUrl($this->image_path);
    }

    /**
     * Badge class of the page design for this item's tone.
     */
    public function badgeClass(): string
    {
        return match ($this->tone) {
            'blue' => 'badge-primary',
            'emerald' => 'badge-emerald',
            'dark' => 'badge-dark',
            default => 'badge-amber',
        };
    }

    /**
     * Inline colours of an icon tile for this item's tone.
     */
    public function toneStyle(): string
    {
        return match ($this->tone) {
            'amber' => 'background: var(--amber-light); color: var(--amber);',
            'emerald' => 'background: var(--emerald-light); color: var(--emerald);',
            'orange' => 'background: #fff7ed; color: #ea580c;',
            'violet' => 'background: #f5f3ff; color: #7c3aed;',
            'red' => 'background: #fef2f2; color: #ef4444;',
            'dark' => 'background: #e7e5e4; color: #1c1917;',
            default => 'background: var(--primary-light); color: var(--primary);',
        };
    }
}

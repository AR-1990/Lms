<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use App\Services\WebsitePageService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * One public page (home, about, academics…) or the shared "site" blocks (footer, call-to-action).
 * Its content blocks are fixed by the page design; the admin edits their text, pictures and cards.
 */
#[Fillable(['slug', 'name', 'meta_title', 'meta_description', 'hero_pill', 'hero_title', 'hero_text', 'hero_image_path', 'is_published'])]
class WebsitePage extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'website';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(WebsiteSection::class)->orderBy('sort_order');
    }

    /**
     * An active block of this page by its key, or null (the page then skips that block).
     */
    public function section(string $key): ?WebsiteSection
    {
        return $this->sections->first(fn (WebsiteSection $section): bool => $section->section_key === $key && $section->is_active);
    }

    /**
     * Seeded pictures live in public/images; uploaded ones on the public disk.
     */
    public static function imageUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return str_starts_with($path, 'images/') ? asset($path) : Storage::disk('public')->url($path);
    }

    /**
     * Titles mark their coloured words with [brackets]: "Inspiring [Global Leaders]".
     */
    public static function highlight(?string $text): HtmlString
    {
        return new HtmlString(preg_replace('/\[(.+?)\]/', '<span class="text-amber-gradient">$1</span>', e((string) $text)));
    }

    /**
     * Stored links are site paths ("/admissions", "/login?role=student") or full addresses.
     */
    public static function link(?string $url): string
    {
        return $url === null || $url === '' ? '#' : (str_starts_with($url, '/') ? url($url) : $url);
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

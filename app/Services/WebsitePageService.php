<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\WebsitePage;
use App\Models\WebsiteSection;
use App\Models\WebsiteSectionItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Website pages for the public site and for the admin: page hero / SEO text, the fixed content blocks of
 * each page, and the cards inside a block. Public pages read only active blocks and cards.
 */
class WebsitePageService
{
    /**
     * A public page with its blocks, cards and their child items (an unpublished page is not found).
     */
    public function page(string $slug): WebsitePage
    {
        return WebsitePage::where('slug', $slug)->where('is_published', true)
            ->with(['sections.items' => fn ($query) => $query->where('is_active', true)->with(['children' => fn ($children) => $children->where('is_active', true)])])
            ->first() ?? new WebsitePage(['slug' => $slug]);
    }

    /**
     * School details and the shared blocks (footer, social links, call to action) for every public page.
     *
     * @return array{settings: SystemSetting, page: WebsitePage}
     */
    public function site(): array
    {
        return once(fn (): array => [
            'settings' => SystemSetting::query()->first() ?? new SystemSetting,
            'page' => $this->page('site'),
        ]);
    }

    /* ---------- Admin ---------- */

    /**
     * @return Collection<int, WebsitePage>
     */
    public function pages(): Collection
    {
        return WebsitePage::query()->withCount('sections')->orderBy('id')->get(['id', 'slug', 'name', 'is_published', 'updated_at']);
    }

    public function find(int $id): WebsitePage
    {
        return WebsitePage::with(['sections' => fn ($query) => $query->with(['items' => fn ($items) => $items->with('children')])])->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function savePage(WebsitePage $page, array $data): WebsitePage
    {
        DB::transaction(fn () => $page->update($data));

        return $this->find($page->id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveSection(WebsiteSection $section, array $data): WebsiteSection
    {
        DB::transaction(fn () => $section->update($data));

        return $section;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveItem(?WebsiteSectionItem $item, WebsiteSection $section, array $data): WebsiteSectionItem
    {
        if (! empty($data['parent_id']) && WebsiteSectionItem::where('website_section_id', $section->id)->whereKey($data['parent_id'])->whereNull('parent_id')->doesntExist()) {
            throw ValidationException::withMessages(['parent_id' => ['Choose a card of the same block.']]);
        }

        return DB::transaction(function () use ($item, $section, $data): WebsiteSectionItem {
            if ($item === null) {
                $data['sort_order'] ??= (int) WebsiteSectionItem::where('website_section_id', $section->id)->where('parent_id', $data['parent_id'] ?? null)->max('sort_order') + 1;

                return WebsiteSectionItem::create([...$data, 'website_section_id' => $section->id]);
            }

            $item->update($data);

            return $item;
        });
    }

    /**
     * A card goes with its own bullet points (soft deleted).
     */
    public function deleteItem(WebsiteSectionItem $item): void
    {
        DB::transaction(function () use ($item): void {
            $item->children()->get()->each->delete();
            $item->delete();
        });
    }

    /**
     * Store a new picture on the public disk and point the page, block or card at it (the old file stays).
     */
    public function uploadImage(WebsitePage|WebsiteSection|WebsiteSectionItem $record, UploadedFile $image): string
    {
        $path = $image->store('website', 'public');
        $record->update([$record instanceof WebsitePage ? 'hero_image_path' : 'image_path' => $path]);

        return WebsitePage::imageUrl($path);
    }
}

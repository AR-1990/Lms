<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Website\SaveWebsiteItemRequest;
use App\Http\Requests\Website\SaveWebsitePageRequest;
use App\Http\Requests\Website\SaveWebsiteSectionRequest;
use App\Models\WebsitePage;
use App\Models\WebsiteSection;
use App\Models\WebsiteSectionItem;
use App\Services\WebsitePageService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Website pages (manage-website): page hero and SEO text, the text of each content block, the cards
 * inside a block, and pictures. Blocks are fixed by the page design, so they are edited, not added.
 */
class WebsitePageController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private WebsitePageService $pageService) {}

    public function index(): JsonResponse
    {
        return $this->successResponse($this->pageService->pages(), 'Pages retrieved.');
    }

    public function show(WebsitePage $page): JsonResponse
    {
        return $this->successResponse($this->pageService->present($this->pageService->find($page->id)), 'Page retrieved.');
    }

    public function update(SaveWebsitePageRequest $request, WebsitePage $page): JsonResponse
    {
        return $this->successResponse($this->pageService->present($this->pageService->savePage($page, $request->validated())), 'Page saved; the website shows it now.');
    }

    public function updateSection(SaveWebsiteSectionRequest $request, WebsiteSection $section): JsonResponse
    {
        return $this->successResponse($this->pageService->saveSection($section, $request->validated()), 'Block saved.');
    }

    public function storeItem(SaveWebsiteItemRequest $request, WebsiteSection $section): JsonResponse
    {
        return $this->successResponse($this->pageService->saveItem(null, $section, $request->validated()), 'Card added.', 201);
    }

    public function updateItem(SaveWebsiteItemRequest $request, WebsiteSectionItem $item): JsonResponse
    {
        return $this->successResponse($this->pageService->saveItem($item, WebsiteSection::findOrFail($item->website_section_id), $request->validated()), 'Card saved.');
    }

    public function destroyItem(WebsiteSectionItem $item): JsonResponse
    {
        return $this->deleteWithApproval($item, 'Card removed from the website.');
    }

    /**
     * Upload a picture for a page hero (type page), a block (section) or a card (item).
     */
    public function image(Request $request, string $kind, int $id): JsonResponse
    {
        $request->validate(['image' => ['required', 'image', 'max:4096']]);
        $record = match ($kind) {
            'page' => WebsitePage::findOrFail($id),
            'section' => WebsiteSection::findOrFail($id),
            'item' => WebsiteSectionItem::findOrFail($id),
            default => abort(404),
        };

        return $this->successResponse(['url' => $this->pageService->uploadImage($record, $request->file('image'))], 'Picture uploaded.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\PublicSiteService;
use App\Services\SystemSettingsService;
use App\Services\WebsitePageService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Public website content for the mobile apps (no sign-in): the same data the website pages show.
 */
class PublicSiteController extends Controller
{
    use ApiResponse;

    public function __construct(private PublicSiteService $siteService, private WebsitePageService $pageService) {}

    /**
     * School details, shared blocks and the home page content.
     */
    public function index(): JsonResponse
    {
        $site = $this->pageService->site();

        return $this->successResponse([
            'school' => [...$site['settings']->only(['school_name', 'school_tagline', 'school_address', 'school_phone', 'admissions_phone', 'whatsapp_number', 'contact_email', 'admissions_email', 'office_hours', 'visiting_hours', 'footer_text']), 'logo_url' => app(SystemSettingsService::class)->logoFile() ? route('school-logo') : null],
            'shared' => $this->pageService->present($site['page']),
            'home' => $this->siteService->home(),
        ], 'Site retrieved.');
    }

    public function page(string $slug): JsonResponse
    {
        $page = $this->pageService->page($slug);
        abort_unless($page->exists, 404);

        return $this->successResponse($this->pageService->present($page), 'Page retrieved.');
    }

    public function news(): JsonResponse
    {
        return $this->successResponse($this->siteService->news(), 'News retrieved.');
    }

    public function contact(): JsonResponse
    {
        return $this->successResponse($this->siteService->contact(), 'Contact details retrieved.');
    }
}

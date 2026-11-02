<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Services\PublicSiteService;
use App\Services\PushService;
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

    /**
     * What the mobile app needs after the user enters this school's address, before sign-in: name, logo
     * (the URL changes with each upload, so the phone keeps its saved copy until then) and the live-updates server.
     */
    public function appConfig(PushService $pushService): JsonResponse
    {
        $settings = SystemSetting::query()->first(['school_name', 'school_tagline', 'logo_path']);
        $reverb = config('broadcasting.connections.reverb');

        return $this->successResponse([
            'school_name' => $settings?->school_name ?? config('app.name'),
            'school_tagline' => $settings?->school_tagline,
            'logo_url' => $settings?->logo_path ? route('school-logo', ['v' => basename($settings->logo_path)]) : null,
            'logo_version' => $settings?->logo_path ? basename($settings->logo_path) : null,
            'realtime' => config('broadcasting.default') === 'reverb' ? [
                'key' => $reverb['key'],
                'host' => $reverb['options']['host'],
                'port' => (int) $reverb['options']['port'],
                'scheme' => $reverb['options']['scheme'],
                'auth_url' => url('/api/broadcasting/auth'),
            ] : null,
            'push_enabled' => $pushService->isConfigured(),
        ], 'App settings retrieved.');
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

<?php

namespace App\Providers;

use App\Services\SystemSettingsService;
use App\Services\WebsitePageService;
use App\Support\GridDataTable;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Every grid gets the date range and safe per-column search (see GridDataTable).
        config(['datatables.engines.eloquent' => GridDataTable::class]);

        app(SystemSettingsService::class)->applyRuntimeConfiguration();

        // School details and shared website blocks for the public layout and its partials.
        View::composer(['layouts.school', 'pages.*', 'partials.header', 'partials.footer', 'partials.cta-banner'], function ($view): void {
            $site = app(WebsitePageService::class)->site();
            $view->with(['site' => $site, 'settings' => $site['settings']]);
        });
    }
}

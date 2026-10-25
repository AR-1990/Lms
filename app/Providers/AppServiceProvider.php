<?php

namespace App\Providers;

use App\Services\SystemSettingsService;
use App\Services\WebsitePageService;
use App\Support\ApiResponseHelper;
use App\Support\GridDataTable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

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

        // Sign-in and password-reset attempts: 5 a minute per account and IP, 20 a minute per IP. The web form
        // gets its error back on the page; the mobile apps get the standard JSON envelope with status 429.
        foreach (['login' => 'Too many sign-in attempts.', 'password-reset' => 'Too many password reset requests.'] as $name => $message) {
            RateLimiter::for($name, function (Request $request) use ($message): array {
                $identifier = Str::lower((string) ($request->input('username') ?? $request->input('email') ?? $request->input('identifier') ?? ''));
                $tooMany = function (Request $request, array $headers) use ($message) {
                    $text = $message.' Please try again in '.($headers['Retry-After'] ?? 60).' seconds.';

                    return $request->is('api/*') || $request->expectsJson()
                        ? ApiResponseHelper::error($text, 429)->withHeaders($headers)
                        : back()->withErrors(['username' => $text])->onlyInput('username');
                };

                return [
                    Limit::perMinute(5)->by($identifier.'|'.$request->ip())->response($tooMany),
                    Limit::perMinute(20)->by($request->ip())->response($tooMany),
                ];
            });
        }

        // Mobile tokens stay valid while the app is used; one left unused for SANCTUM_IDLE_DAYS stops working,
        // and a disabled account's token is refused.
        Sanctum::authenticateAccessTokensUsing(fn ($token, bool $isValid): bool => $isValid
            && (bool) $token->tokenable?->is_active
            && ($token->last_used_at ?? $token->created_at)->gt(now()->subDays((int) config('sanctum.idle_days', 60))));

        // School details and shared website blocks for the public layout and its partials.
        View::composer(['layouts.school', 'pages.*', 'partials.header', 'partials.footer', 'partials.cta-banner'], function ($view): void {
            $site = app(WebsitePageService::class)->site();
            $view->with(['site' => $site, 'settings' => $site['settings']]);
        });
    }
}

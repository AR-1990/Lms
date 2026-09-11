<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\ForceJsonResponse;
use App\Support\ApiResponseHelper;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            $registerErpPublicRoutes = static function (string $prefix, string $name, string $file): void {
                Route::middleware(['api', ForceJsonResponse::class])
                    ->prefix($prefix)
                    ->name($name)
                    ->group(base_path($file));
            };

            $registerErpProtectedRoutes = static function (string $file): void {
                Route::middleware(['api', ForceJsonResponse::class, 'auth:sanctum'])
                    ->prefix('api/erp')
                    ->name('erp.')
                    ->group(base_path($file));
            };

            $registerErpPublicRoutes('api/erp/auth', 'erp.auth.', 'routes/auth.php');
            $registerErpProtectedRoutes('routes/erp-access.php');
            $registerErpProtectedRoutes('routes/erp-admin.php');
            $registerErpProtectedRoutes('routes/erp-teacher.php');
            $registerErpProtectedRoutes('routes/erp-student.php');
            $registerErpProtectedRoutes('routes/erp-parent.php');
            $registerErpProtectedRoutes('routes/erp-accounts.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => CheckRole::class,
            'permission' => CheckPermission::class,
            'force.json' => ForceJsonResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return ApiResponseHelper::validation(
                $exception->errors(),
                'Validation failed.',
                $exception->status,
            );
        });
    })->create();

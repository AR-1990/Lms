<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\ForceJsonResponse;
use App\Support\ApiResponseHelper;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Exports first: their fixed paths (e.g. id-cards/print) must win over API wildcards.
            Route::group([], base_path('routes/export.php'));
            Route::middleware('api')->prefix('api')->group(base_path('routes/api.php'));
        },
    )
    // Channel auth at /api/broadcasting/auth: the web session and mobile Sanctum tokens both work.
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => CheckRole::class,
            'permission' => CheckPermission::class,
            'force.json' => ForceJsonResponse::class,
        ]);

        $middleware->encryptCookies(except: ['dash_sidebar']);

        // Admin web screens call the same /api/erp endpoints as the mobile app, using the session cookie.
        $middleware->statefulApi();
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

        // Every other API error also uses the standard envelope (AGENTS.md rule 3).
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            return $request->is('api/*') ? ApiResponseHelper::unauthenticated() : null;
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            return $request->is('api/*')
                ? ApiResponseHelper::error('You do not have permission to perform this action.', 403)
                : null;
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            return $request->is('api/*')
                ? ApiResponseHelper::error(class_basename($exception->getModel()).' not found.', 404)
                : null;
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $message = match ($exception->getStatusCode()) {
                404 => 'Endpoint or record not found.',
                405 => 'This action is not allowed on this endpoint.',
                429 => 'Too many requests. Please slow down.',
                default => $exception->getMessage() ?: 'Request could not be completed.',
            };

            return ApiResponseHelper::error($message, $exception->getStatusCode());
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*') || $exception instanceof HttpResponseException) {
                return null;
            }

            return ApiResponseHelper::error(
                config('app.debug') ? $exception->getMessage() : 'Something went wrong. Please try again.',
                500,
            );
        });
    })->create();

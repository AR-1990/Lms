<?php

namespace App\Http\Middleware;

use App\Support\ApiResponseHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();
        $wantsJson = $request->expectsJson() || $request->is('api/*');

        if (! $user) {
            if ($wantsJson) {
                return ApiResponseHelper::unauthenticated();
            }

            return redirect()->guest(route('login'));
        }

        if (! $user->hasRole($roles)) {
            if ($wantsJson) {
                return ApiResponseHelper::forbidden(
                    'Forbidden: You do not have the required role to access this resource.',
                    'role',
                    'Required role(s): '.implode(', ', (array) $roles),
                );
            }

            abort(403, 'You do not have permission to access this area.');
        }

        return $next($request);
    }
}

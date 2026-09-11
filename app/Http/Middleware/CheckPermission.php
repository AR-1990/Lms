<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string  ...$permissions
     */
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        $user = $request->user();
        $wantsJson = $request->expectsJson() || $request->is('api/*');

        if (! $user) {
            if ($wantsJson) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                    'data' => null,
                    'errors' => ['auth' => ['You must be logged in to perform this action.']],
                ], 401);
            }

            return redirect()->guest(route('login'));
        }

        if (! $user->hasPermission($permissions)) {
            if ($wantsJson) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: You do not have the required permission to access this resource.',
                    'data' => null,
                    'errors' => [
                        'permission' => [
                            'Required permission(s): '.implode(', ', (array) $permissions),
                        ],
                    ],
                ], 403);
            }

            abort(403, 'You do not have permission to access this area.');
        }

        return $next($request);
    }
}

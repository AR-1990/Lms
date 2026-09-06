<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
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

        if (! $user->hasRole($roles)) {
            if ($wantsJson) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: You do not have the required role to access this resource.',
                    'data' => null,
                    'errors' => [
                        'role' => [
                            'Required role(s): '.implode(', ', (array) $roles),
                        ],
                    ],
                ], 403);
            }

            abort(403, 'You do not have permission to access this area.');
        }

        return $next($request);
    }
}

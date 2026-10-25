<?php

namespace App\Http\Middleware;

use App\Support\ApiResponseHelper;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A web session whose account has been disabled is ended on its next request (any session driver). Mobile
 * tokens of a disabled account are refused in AppServiceProvider and deleted when the account is disabled.
 */
class EnsureAccountIsActive
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if ($user !== null && ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $message = 'This account has been disabled. Please contact the school office.';

            return $request->is('api/*') || $request->expectsJson()
                ? ApiResponseHelper::unauthenticated($message)
                : redirect()->route('login')->withErrors(['username' => $message]);
        }

        return $next($request);
    }
}

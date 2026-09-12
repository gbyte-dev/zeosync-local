<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAuthenticated
{
    /**
     * Handle an incoming request for protected admin routes.
     *
     * Flow:
     * 1. Authenticated Admin (auth:admin guard) -> Allow ($next)
     * 2. Authenticated Normal / Non-Admin User (auth:web guard, shop session, etc.) -> 403 Forbidden
     * 3. Unauthenticated Guest -> Redirect to admin.login (or 401 for JSON requests)
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Authenticated as Admin
        if (Auth::guard('admin')->check()) {
            return $next($request);
        }

        // 2. Authenticated as a normal / non-admin user
        if (
            Auth::guard('web')->check() ||
            Auth::check() ||
            $request->user('web') !== null ||
            $request->user() !== null ||
            $request->session()->has('active_shop') ||
            $request->session()->has('active_shop_id') ||
            $request->session()->has('shop')
        ) {
            abort(Response::HTTP_FORBIDDEN, 'Unauthorized access to admin panel.');
        }

        // 3. Unauthenticated Guest
        if ($request->expectsJson()) {
            abort(Response::HTTP_UNAUTHORIZED, 'Unauthenticated.');
        }

        return redirect()->guest(route('admin.login'));
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class VerifyAdminRequest
{
    /**
     * Handle an incoming request.
     * - Ensures requests to admin routes are same-origin for state-changing methods
     * - Optionally restricts by configured allowed IPs
     */
    public function handle(Request $request, Closure $next)
    {
        // Optional: restrict by configured allowed IPs (comma-separated in config/admin.php)
        $allowed = Config::get('admin.allowed_ips');
        if (!empty($allowed)) {
            $allowedList = array_map('trim', explode(',', (string) $allowed));
            $clientIp = $request->ip();
            if (!in_array($clientIp, $allowedList)) {
                abort(Response::HTTP_FORBIDDEN, 'Access denied');
            }
        }

        // For state-changing requests, enforce same-origin (basic protection against external triggers)
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $origin = $request->headers->get('origin') ?? $request->headers->get('referer');
            if ($origin) {
                $appUrl = rtrim(Config::get('app.url', ''), '/');
                // allow if origin matches app url
                if (stripos($origin, $appUrl) === false) {
                    abort(Response::HTTP_FORBIDDEN, 'Invalid request origin');
                }
            }
        }

        return $next($request);
    }
}

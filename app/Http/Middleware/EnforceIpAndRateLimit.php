<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;

class EnforceIpAndRateLimit
{
    protected RateLimiter $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request.
     * Usage: middleware('ip.rate:60,1') => 60 attempts per 1 minute
     */
    public function handle(Request $request, Closure $next, $maxAttempts = 60, $decayMinutes = 1)
    {
        // Resolve client IP (works with trusted proxies if configured)
        $ip = $request->ip();

        // If there is no IP, reject immediately
        if (empty($ip)) {
            return response()->json(['message' => 'Client IP required.'], 403);
        }

        $key = 'ip-rate:' . $ip;
        $maxAttempts = (int) $maxAttempts;
        $decaySeconds = (int) $decayMinutes * 60;

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = $this->limiter->availableIn($key);
            return response()->json([
                'message' => 'Too many requests. Retry after ' . $retryAfter . ' seconds.'
            ], 429)->header('Retry-After', $retryAfter);
        }

        $this->limiter->hit($key, $decaySeconds);

        $response = $next($request);

        return $response;
    }
}
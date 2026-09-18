<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $nonce = base64_encode(random_bytes(16));

        // Make nonce available to Blade views
        view()->share('cspNonce', $nonce);

        $response = $next($request);

        $csp = implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self' https://admin.shopify.com https://*.myshopify.com",

            "script-src 'self' 'nonce-{$nonce}' https://cdn.shopify.com https://cdn.jsdelivr.net https://code.jquery.com https://cdn.datatables.net https://cdnjs.cloudflare.com",

            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com https://cdn.datatables.net",

            "img-src 'self' data: blob: https://*.shopify.com https://*.myshopify.com https://shopify.com https://cdn.shopify.com https://*.amazonaws.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",

            "font-src 'self' data: https://fonts.gstatic.com https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",

            "connect-src 'self' https://api.stripe.com https://*.shopify.com https://*.myshopify.com https://cdn.jsdelivr.net",

            "frame-src 'self' https://*.shopify.com https://*.myshopify.com",

            "form-action 'self' https://*.myshopify.com",

            "upgrade-insecure-requests",
        ]);

        $response->headers->remove('X-Powered-By');

        $response->headers->set(
            'X-Content-Type-Options',
            'nosniff'
        );

        $response->headers->set(
            'Referrer-Policy',
            'strict-origin-when-cross-origin'
        );

        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=()'
        );

        $response->headers->set(
            'Content-Security-Policy',
            $csp
        );

        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
}
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Header keamanan HTTP (spesifikasi §16.3). */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $dev = app()->environment('local') && file_exists(public_path('hot'));
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'" . ($dev ? ' http://localhost:5173 http://127.0.0.1:5173' : ''),
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com" . ($dev ? ' http://localhost:5173' : ''),
            "font-src 'self' data: https://fonts.gstatic.com",
            "img-src 'self' data: blob:",
            "connect-src 'self'" . ($dev ? ' ws://localhost:5173 http://localhost:5173 ws://127.0.0.1:5173' : ''),
            "frame-ancestors 'none'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ]);
        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        if (!$response->headers->has('Cache-Control') || str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        }
        return $response;
    }
}

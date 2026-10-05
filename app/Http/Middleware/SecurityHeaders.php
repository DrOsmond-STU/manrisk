<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Header keamanan HTTP (spesifikasi §16.3, OWASP ASVS V14). */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $dev = app()->environment('local') && file_exists(public_path('hot'));
        $vite = $dev ? ' http://localhost:5173 http://127.0.0.1:5173' : '';
        $csp = [
            "default-src 'self'",
            // Produksi: tanpa 'unsafe-inline' untuk skrip (aset Vite dimuat dari berkas, tanpa skrip inline)
            "script-src 'self'" . ($dev ? " 'unsafe-inline'" . $vite : ''),
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com" . $vite,
            "font-src 'self' data: https://fonts.gstatic.com",
            "img-src 'self' data: blob:",
            "connect-src 'self'" . ($dev ? ' ws://localhost:5173 ws://127.0.0.1:5173' . $vite : ''),
            "frame-ancestors 'none'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "worker-src 'self' blob:",
            "manifest-src 'self'",
        ];
        if ($request->isSecure()) {
            $csp[] = 'upgrade-insecure-requests';
        }
        $h = $response->headers;
        $h->set('Content-Security-Policy', implode('; ', $csp));
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'DENY');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');
        $h->set('Cross-Origin-Resource-Policy', 'same-origin');
        $h->set('X-Permitted-Cross-Domain-Policies', 'none');
        $h->remove('X-Powered-By');
        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        $type = (string) $h->get('Content-Type');
        if (!$h->has('Content-Disposition') && (str_contains($type, 'text/html') || str_contains($type, 'json') || $type === '')) {
            $h->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $h->set('Pragma', 'no-cache');
        }
        if ($h->has('Content-Disposition')) {
            $h->set('Cache-Control', 'private, no-store');
        }
        return $response;
    }
}

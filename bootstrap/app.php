<?php

use App\Http\Middleware\EnforceSessionPolicy;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequirePasswordChange;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Hanya proksi lokal/privat yang dipercaya; header X-Forwarded-* dari klien publik diabaikan
        // agar IP tidak dapat dipalsukan untuk melewati pembatasan login.
        $middleware->trustProxies(at: array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1,::1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16')))));
        $middleware->web(append: [
            SecurityHeaders::class,
            HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);
        $middleware->alias([
            'session.policy' => EnforceSessionPolicy::class,
            'password.fresh' => RequirePasswordChange::class,
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/dashboard');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $e, Request $request) {
            if ($response->getStatusCode() === 403 && $request->user()) {
                \App\Models\AuthLog::write('access_denied', $request->user()->email, $request->user(), mb_substr($request->method() . ' ' . $request->path(), 0, 200));
            }
            if ($request->header('X-Inertia') && in_array($response->getStatusCode(), [403, 404, 419, 429, 500, 503], true)) {
                if ($response->getStatusCode() === 419) {
                    return back()->with('error', 'Sesi kedaluwarsa, silakan coba lagi.');
                }
                return \Inertia\Inertia::render('Error', ['status' => $response->getStatusCode()])
                    ->toResponse($request)->setStatusCode($response->getStatusCode());
            }
            return $response;
        });
    })->create();

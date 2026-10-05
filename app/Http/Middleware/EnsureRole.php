<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Pembatasan rute berdasarkan peran: middleware('role:super_admin,risk_admin'). */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless($request->user() && $request->user()->hasRole(...$roles), 403, 'Anda tidak memiliki akses ke halaman ini.');
        return $next($request);
    }
}

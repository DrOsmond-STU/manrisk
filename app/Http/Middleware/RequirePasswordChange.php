<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Pengguna dengan must_change_password hanya boleh membuka halaman ganti sandi. */
class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->must_change_password && !$request->routeIs('password.*', 'logout')) {
            return redirect()->route('password.edit')->with('warning', 'Anda wajib mengganti kata sandi sebelum melanjutkan.');
        }
        return $next($request);
    }
}

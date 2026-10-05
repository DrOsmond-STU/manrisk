<?php

namespace App\Http\Middleware;

use App\Models\AuthLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Batas sesi: idle 30 menit, absolut 8 jam (spesifikasi §16.1); akun nonaktif langsung keluar. */
class EnforceSessionPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user) {
            $now = now()->timestamp;
            $last = (int) $request->session()->get('mr_last_activity', $now);
            $start = (int) $request->session()->get('mr_login_at', $now);
            $idle = (int) config('manrisk.session_idle_minutes') * 60;
            $absolute = (int) config('manrisk.session_absolute_hours') * 3600;
            $reason = null;
            if (!$user->active) {
                $reason = 'account_disabled';
            } elseif ($now - $last > $idle) {
                $reason = 'idle_timeout';
            } elseif ($now - $start > $absolute) {
                $reason = 'absolute_timeout';
            }
            if ($reason) {
                AuthLog::write('session_expired', $user->email, $user, $reason);
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                $msg = $reason === 'account_disabled' ? 'Akun Anda dinonaktifkan.' : 'Sesi berakhir, silakan masuk kembali.';
                if ($request->header('X-Inertia')) {
                    return \Inertia\Inertia::location(route('login'));
                }
                return redirect()->route('login')->with('error', $msg);
            }
            $request->session()->put('mr_last_activity', $now);
            if (!$request->session()->has('mr_login_at')) {
                $request->session()->put('mr_login_at', $now);
            }
        }
        return $next($request);
    }
}

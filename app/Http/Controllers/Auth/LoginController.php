<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuthLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Login/logout server-side (spesifikasi §16.1): kunci 15 menit setelah 5 kali gagal
 * (per email+IP), regenerasi sesi, pencatatan auth_logs, pesan galat generik.
 */
class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', ['status' => session('error') ?? session('status')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:160'],
            'password' => ['required', 'string', 'max:200'],
            'remember' => ['nullable', 'boolean'],
        ]);
        $email = Str::lower(trim($data['email']));
        $key = 'login:' . sha1($email . '|' . $request->ip());
        $max = (int) config('manrisk.login_max_attempts');
        $lock = (int) config('manrisk.login_lock_minutes') * 60;

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $wait = (int) ceil(RateLimiter::availableIn($key) / 60);
            AuthLog::write('login_locked', $email, null, "ip={$request->ip()}");
            throw ValidationException::withMessages(['email' => "Terlalu banyak percobaan. Coba lagi dalam {$wait} menit."]);
        }

        $user = User::withoutGlobalScopes()->where('email', $email)->first();
        $ok = $user && $user->active && Auth::attempt(['email' => $email, 'password' => $data['password'], 'active' => true], (bool) ($data['remember'] ?? false));
        if (!$ok) {
            RateLimiter::hit($key, $lock);
            AuthLog::write('login_failed', $email, $user?->active ? $user : null, $user ? ($user->active ? 'wrong_password' : 'inactive') : 'unknown_email');
            throw ValidationException::withMessages(['email' => 'Email atau kata sandi salah.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->put(['mr_login_at' => now()->timestamp, 'mr_last_activity' => now()->timestamp]);
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();
        AuthLog::write('login_success', $email, $user);

        if ($user->must_change_password) {
            return redirect()->route('password.edit')->with('warning', 'Anda wajib mengganti kata sandi sebelum melanjutkan.');
        }
        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        if ($user = $request->user()) {
            AuthLog::write('logout', $user->email, $user);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login')->with('status', 'Anda telah keluar.');
    }
}

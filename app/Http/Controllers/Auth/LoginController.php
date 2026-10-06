<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuthLog;
use App\Models\User;
use App\Support\Mfa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Login/logout server-side (spesifikasi §16.1).
 * Tiga lapis pembatas: email+IP (5 gagal → kunci 15 menit), email saja (10 gagal, tahan
 * terhadap rotasi IP), dan IP saja (30 gagal, tahan terhadap penebakan banyak akun).
 * Waktu respons dibuat seragam untuk email terdaftar maupun tidak (anti-enumerasi).
 * Bila MFA berlaku, login baru selesai setelah kode OTP email diverifikasi (MfaController).
 * Tidak ada "ingat saya" agar batas sesi absolut 8 jam tidak dapat dilewati.
 */
class LoginController extends Controller
{
    private const DUMMY_HASH = '$2y$12$EToSxVE4PH4EJCvnyW7S5O/pwl.bUa3ShJXXo.V79omsfW9.nGING';

    public function create(): Response
    {
        return Inertia::render('Auth/Login', ['status' => session('error') ?? session('status')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:160'],
            'password' => ['required', 'string', 'max:200'],
        ]);
        $email = Str::lower(trim($data['email']));
        $ip = (string) $request->ip();
        $lock = (int) config('manrisk.login_lock_minutes') * 60;
        $keys = [
            'login:' . sha1($email . '|' . $ip) => (int) config('manrisk.login_max_attempts'),
            'login-email:' . sha1($email) => (int) config('manrisk.login_max_attempts') * 2,
            'login-ip:' . sha1($ip) => (int) config('manrisk.login_max_attempts') * 6,
        ];
        foreach ($keys as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $wait = (int) ceil(RateLimiter::availableIn($key) / 60);
                AuthLog::write('login_locked', $email, null, 'ip=' . $ip);
                throw ValidationException::withMessages(['email' => "Terlalu banyak percobaan. Coba lagi dalam {$wait} menit."]);
            }
        }

        $user = User::withoutGlobalScopes()->whereNull('deleted_at')->where('email', $email)->first();
        // Selalu menjalankan verifikasi hash (dengan hash palsu bila akun tidak ada) agar waktu seragam
        $valid = Hash::check($data['password'], $user?->password ?? self::DUMMY_HASH);
        if (!$user || !$valid || !$user->active) {
            foreach (array_keys($keys) as $key) {
                RateLimiter::hit($key, $lock);
            }
            AuthLog::write('login_failed', $email, $user, $user ? ($user->active ? 'wrong_password' : 'inactive') : 'unknown_email');
            throw ValidationException::withMessages(['email' => 'Email atau kata sandi salah.']);
        }

        foreach (array_keys($keys) as $key) {
            if (!str_starts_with($key, 'login-ip:')) {
                RateLimiter::clear($key);
            }
        }
        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => $data['password']])->saveQuietly();
        }

        // Langkah kedua: kode OTP via email bila MFA berlaku dan perangkat belum tepercaya
        if (Mfa::active($user)) {
            if (Mfa::isTrusted($user, $request)) {
                return self::finish($request, $user, 'trusted_device');
            }
            $request->session()->regenerate();
            $request->session()->put('mfa_pending', ['id' => $user->id, 'at' => now()->timestamp]);
            $sent = Mfa::send($user, Mfa::PURPOSE_LOGIN);
            AuthLog::write('mfa_challenge', $email, $user, $sent['ok'] ? 'code_sent' : ($sent['reason'] ?? 'send_failed'));
            if (($sent['reason'] ?? null) === 'cooldown') {
                // Kode sebelumnya masih berlaku; jangan kirim ulang terlalu cepat
                return redirect()->route('mfa.challenge')->with('status', 'Kode verifikasi sudah dikirim sebelumnya. Periksa email Anda.');
            }
            return redirect()->route('mfa.challenge')->with($sent['ok'] ? 'status' : 'error', $sent['message']);
        }
        return self::finish($request, $user, 'password');
    }

    /** Selesaikan login setelah semua faktor terverifikasi. */
    public static function finish(Request $request, User $user, string $via): RedirectResponse
    {
        Auth::login($user);
        $request->session()->forget('mfa_pending');
        $request->session()->regenerate();
        $request->session()->regenerateToken();
        $request->session()->put(['mr_login_at' => now()->timestamp, 'mr_last_activity' => now()->timestamp]);
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => (string) $request->ip()])->saveQuietly();
        AuthLog::write('login_success', $user->email, $user, $via === 'password' ? null : "via={$via}");

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

    /** Keluar dari semua perangkat lain (§16.1). */
    public function destroyOthers(Request $request): RedirectResponse
    {
        $user = $request->user();
        $n = \App\Support\SessionManager::revokeAll($user, $request->session()->getId());
        AuthLog::write('logout_other_devices', $user->email, $user, "sessions={$n}");
        return back()->with('success', $n ? "{$n} sesi lain telah diakhiri." : 'Tidak ada sesi lain yang aktif.');
    }
}

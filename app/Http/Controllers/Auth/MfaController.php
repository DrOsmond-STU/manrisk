<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuthLog;
use App\Models\User;
use App\Support\Mfa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Langkah kedua login: verifikasi kode OTP yang dikirim lewat email, atau kode pemulihan.
 * Status "menunggu MFA" disimpan di sesi tamu (belum terautentikasi) dan kedaluwarsa 10 menit.
 */
class MfaController extends Controller
{
    private const PENDING_MINUTES = 10;
    private const MAX_FAILS = 10;

    public function show(Request $request)
    {
        $user = $this->pending($request);
        if (!$user) {
            return redirect()->route('login')->with('error', 'Sesi verifikasi berakhir. Silakan masuk kembali.');
        }
        return Inertia::render('Auth/MfaVerify', [
            'email' => Mfa::maskEmail($user->email),
            'ttl' => Mfa::ttlMinutes(),
            'wait' => Mfa::resendWait($user, Mfa::PURPOSE_LOGIN),
            'trust_days' => Mfa::trustDays($user),
            'has_recovery' => Mfa::recoveryRemaining($user) > 0,
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $user = $this->pending($request);
        if (!$user) {
            return redirect()->route('login')->with('error', 'Sesi verifikasi berakhir. Silakan masuk kembali.');
        }
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'mode' => ['required', 'in:otp,recovery'],
            'trust' => ['nullable', 'boolean'],
        ]);
        $key = 'mfa-verify:' . $user->id;
        if (RateLimiter::tooManyAttempts($key, self::MAX_FAILS)) {
            $request->session()->forget('mfa_pending');
            AuthLog::write('mfa_locked', $user->email, $user);
            return redirect()->route('login')->with('error', 'Terlalu banyak kode salah. Coba masuk kembali dalam ' . (int) ceil(RateLimiter::availableIn($key) / 60) . ' menit.');
        }

        $ok = $data['mode'] === 'recovery' ? Mfa::useRecoveryCode($user, $data['code']) : Mfa::check($user, $data['code'], Mfa::PURPOSE_LOGIN);
        if (!$ok) {
            RateLimiter::hit($key, 15 * 60);
            AuthLog::write('mfa_failed', $user->email, $user, $data['mode']);
            throw ValidationException::withMessages(['code' => $data['mode'] === 'recovery' ? 'Kode pemulihan tidak valid atau sudah dipakai.' : 'Kode salah atau sudah kedaluwarsa.']);
        }
        RateLimiter::clear($key);

        if ($data['mode'] === 'recovery') {
            $left = Mfa::recoveryRemaining($user);
            AuthLog::write('mfa_recovery_used', $user->email, $user, "remaining={$left}");
            Mfa::notify($user, 'Kode pemulihan dipakai untuk masuk', "Sebuah kode pemulihan baru saja dipakai untuk masuk ke akun Anda. Sisa kode pemulihan: {$left}.");
        }
        if (!empty($data['trust']) && ($days = Mfa::trustDays($user)) > 0) {
            Mfa::trustDevice($user, $request, $days);
        }
        $response = LoginController::finish($request, $user, $data['mode'] === 'recovery' ? 'mfa_recovery' : 'mfa_email');
        if ($data['mode'] === 'recovery' && Mfa::recoveryRemaining($user) <= 2 && !$user->must_change_password) {
            $response->with('warning', 'Sisa kode pemulihan Anda tinggal ' . Mfa::recoveryRemaining($user) . '. Buat kode baru di halaman Profil.');
        }
        return $response;
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $this->pending($request);
        if (!$user) {
            return redirect()->route('login')->with('error', 'Sesi verifikasi berakhir. Silakan masuk kembali.');
        }
        $sent = Mfa::send($user, Mfa::PURPOSE_LOGIN);
        return back()->with($sent['ok'] ? 'status' : 'error', $sent['message']);
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget('mfa_pending');
        return redirect()->route('login');
    }

    private function pending(Request $request): ?User
    {
        $p = $request->session()->get('mfa_pending');
        if (!is_array($p) || empty($p['id']) || now()->timestamp - (int) ($p['at'] ?? 0) > self::PENDING_MINUTES * 60) {
            $request->session()->forget('mfa_pending');
            return null;
        }
        $user = User::withoutGlobalScopes()->whereNull('deleted_at')->where('active', true)->find($p['id']);
        if (!$user) {
            $request->session()->forget('mfa_pending');
        }
        return $user;
    }
}

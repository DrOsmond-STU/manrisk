<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuthLog;
use App\Support\MailSettings;
use App\Support\Mfa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Pengelolaan verifikasi dua langkah oleh pemilik akun (halaman Profil). */
class MfaSettingsController extends Controller
{
    /** Kirim kode aktivasi ke email akun (membuktikan email dapat menerima kode). */
    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!MailSettings::ready()) {
            return back()->with('error', 'Server email (SMTP) belum dikonfigurasi. Hubungi Super Admin.');
        }
        $sent = Mfa::send($user, Mfa::PURPOSE_ENABLE);
        return back()->with($sent['ok'] ? 'success' : 'error', $sent['message']);
    }

    public function enable(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate(['code' => ['required', 'string', 'max:12']]);
        if (!Mfa::check($user, $data['code'], Mfa::PURPOSE_ENABLE)) {
            AuthLog::write('mfa_failed', $user->email, $user, 'enable');
            throw ValidationException::withMessages(['code' => 'Kode salah atau sudah kedaluwarsa.']);
        }
        $user->forceFill(['mfa_enabled' => true, 'mfa_enabled_at' => now()])->save();
        $codes = Mfa::newRecoveryCodes($user);
        AuthLog::write('mfa_enabled', $user->email, $user);
        Mfa::notify($user, 'Verifikasi dua langkah diaktifkan', 'Verifikasi dua langkah (kode via email) kini aktif untuk akun ManRisk Anda. Setiap kali masuk, Anda akan diminta kode yang dikirim ke email ini.');
        return back()->with('success', 'Verifikasi dua langkah aktif. Simpan kode pemulihan Anda.')->with('mfa_recovery_codes', $codes);
    }

    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->confirmPassword($request);
        if (Mfa::requiredByPolicy($user)) {
            return back()->with('error', 'Verifikasi dua langkah diwajibkan kebijakan organisasi untuk peran Anda dan tidak dapat dinonaktifkan.');
        }
        Mfa::reset($user);
        AuthLog::write('mfa_disabled', $user->email, $user);
        Mfa::notify($user, 'Verifikasi dua langkah dinonaktifkan', 'Verifikasi dua langkah untuk akun ManRisk Anda telah dinonaktifkan. Akun kini hanya dilindungi kata sandi.');
        return back()->with('success', 'Verifikasi dua langkah dinonaktifkan.');
    }

    public function recovery(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->confirmPassword($request);
        abort_unless(Mfa::active($user), 422, 'Verifikasi dua langkah belum aktif.');
        $codes = Mfa::newRecoveryCodes($user);
        AuthLog::write('mfa_recovery_regenerated', $user->email, $user);
        return back()->with('success', 'Kode pemulihan baru dibuat; kode lama tidak berlaku lagi.')->with('mfa_recovery_codes', $codes);
    }

    public function forgetDevices(Request $request): RedirectResponse
    {
        $user = $request->user();
        $n = Mfa::forgetDevices($user);
        AuthLog::write('mfa_devices_forgotten', $user->email, $user, "devices={$n}");
        return back()->with('success', $n ? "{$n} perangkat tepercaya dihapus." : 'Tidak ada perangkat tepercaya.');
    }

    private function confirmPassword(Request $request): void
    {
        $request->validate(['password' => ['required', 'string', 'max:200']]);
        if (!Hash::check($request->input('password'), $request->user()->password)) {
            AuthLog::write('mfa_password_confirm_failed', $request->user()->email, $request->user());
            throw ValidationException::withMessages(['password' => 'Kata sandi salah.']);
        }
    }
}

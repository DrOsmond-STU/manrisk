<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\SecurityNoticeMail;
use App\Models\AuthLog;
use App\Support\MailSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Konfigurasi server email (SMTP) oleh Super Admin, termasuk kirim email uji. */
class MailSettingsController extends Controller
{
    public function edit()
    {
        return Inertia::render('Settings/Mail', ['mail' => MailSettings::summary(), 'encryptions' => MailSettings::ENCRYPTIONS, 'in_use' => MailSettings::inUseByMfa()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'host' => ['exclude_unless:enabled,true', 'required', 'string', 'max:190', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'port' => ['exclude_unless:enabled,true', 'required', 'integer', 'between:1,65535'],
            'encryption' => ['exclude_unless:enabled,true', 'required', Rule::in(array_keys(MailSettings::ENCRYPTIONS))],
            'username' => ['nullable', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:255'],
            'from_address' => ['exclude_unless:enabled,true', 'required', 'email:rfc', 'max:190'],
            'from_name' => ['nullable', 'string', 'max:120'],
        ], [], ['host' => 'host SMTP', 'port' => 'port', 'from_address' => 'alamat pengirim', 'from_name' => 'nama pengirim']);

        $before = MailSettings::stored();
        if (!$data['enabled']) {
            // Pertahankan isian lama agar mudah diaktifkan kembali
            $data = array_merge($before, ['enabled' => false, 'password' => null]);
        }
        MailSettings::save($data, $request->user());
        if (!MailSettings::ready() && MailSettings::inUseByMfa()) {
            MailSettings::save(array_merge(MailSettings::stored(), ['enabled' => $before['enabled'] ?? false, 'password' => null]), $request->user());
            return back()->with('error', 'Email tidak dapat dinonaktifkan: verifikasi dua langkah sedang dipakai dan membutuhkan pengiriman kode lewat email.');
        }
        AuthLog::write('mail_settings_updated', $request->user()->email, $request->user(), $data['enabled'] ? ($data['host'] . ':' . $data['port']) : 'disabled');
        return back()->with('success', 'Pengaturan email disimpan. Kirim email uji untuk memastikan konfigurasi benar.');
    }

    public function test(Request $request)
    {
        $data = $request->validate(['to' => ['required', 'email:rfc', 'max:190']]);
        // Dibatasi di sini (setelah cek peran) agar tidak dipakai untuk mengirim spam
        $key = 'mail-test:' . $request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->with('error', 'Terlalu banyak email uji. Coba lagi dalam ' . RateLimiter::availableIn($key) . ' detik.');
        }
        RateLimiter::hit($key, 60);
        if (!MailSettings::ready()) {
            return back()->with('error', 'Server email belum dikonfigurasi atau masih mode log.');
        }
        try {
            Mail::to($data['to'])->send(new SecurityNoticeMail($request->user()->name, 'Email uji konfigurasi SMTP', 'Jika Anda menerima email ini, konfigurasi server email ManRisk sudah benar dan kode verifikasi dua langkah dapat dikirim.', (string) $request->ip()));
        } catch (\Throwable $e) {
            report($e);
            AuthLog::write('mail_test_failed', $request->user()->email, $request->user(), mb_substr($e->getMessage(), 0, 200));
            return back()->with('error', 'Email uji gagal: ' . mb_substr(preg_replace('/\s+/', ' ', $e->getMessage()), 0, 300));
        }
        MailSettings::markTested();
        AuthLog::write('mail_test_sent', $request->user()->email, $request->user(), 'to=' . $data['to']);
        return back()->with('success', "Email uji dikirim ke {$data['to']}. Periksa kotak masuk (dan folder spam).");
    }
}

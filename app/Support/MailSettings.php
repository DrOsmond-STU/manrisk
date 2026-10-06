<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

/**
 * Konfigurasi SMTP yang dikelola Super Admin dari aplikasi (tanpa mengubah .env).
 * Sandi SMTP disimpan terenkripsi (APP_KEY). Bila nonaktif, konfigurasi MAIL_* dari .env yang berlaku.
 */
class MailSettings
{
    public const KEY = 'mail';

    public const ENCRYPTIONS = ['ssl' => 'SSL/TLS (port 465)', 'tls' => 'STARTTLS (port 587)', 'none' => 'Tanpa enkripsi'];

    private static ?array $cache = null;

    /** Konfigurasi asli dari .env sebelum ditimpa, agar dapat dipulihkan saat SMTP aplikasi dimatikan. */
    private static ?array $env = null;

    /** Pengaturan tersimpan (sandi masih terenkripsi). */
    public static function stored(): array
    {
        if (self::$cache === null) {
            try {
                self::$cache = SystemSetting::read(self::KEY);
            } catch (\Throwable) {
                self::$cache = []; // tabel belum dimigrasi
            }
        }
        return self::$cache;
    }

    public static function save(array $data, ?User $by = null): void
    {
        $old = self::stored();
        $value = [
            'enabled' => (bool) ($data['enabled'] ?? false),
            'host' => trim((string) ($data['host'] ?? '')),
            'port' => (int) ($data['port'] ?? 587),
            'encryption' => $data['encryption'] ?? 'tls',
            'username' => trim((string) ($data['username'] ?? '')) ?: null,
            // Sandi kosong = pertahankan sandi lama
            'password' => filled($data['password'] ?? null) ? Crypt::encryptString($data['password']) : ($old['password'] ?? null),
            'from_address' => trim((string) ($data['from_address'] ?? '')),
            'from_name' => trim((string) ($data['from_name'] ?? '')) ?: config('manrisk.app_name'),
            'tested_at' => $old['tested_at'] ?? null,
        ];
        SystemSetting::write(self::KEY, $value, $by?->id);
        self::$cache = null;
        self::apply();
    }

    public static function markTested(): void
    {
        $s = self::stored();
        $s['tested_at'] = now()->toIso8601String();
        SystemSetting::write(self::KEY, $s);
        self::$cache = null;
    }

    /** Terapkan pengaturan tersimpan ke konfigurasi mailer. Dipanggil saat MailManager pertama kali dibuat. */
    public static function apply(): void
    {
        $s = self::stored();
        self::$env ??= ['mail.default' => config('mail.default'), 'mail.mailers.smtp' => config('mail.mailers.smtp'), 'mail.from' => config('mail.from')];
        if (empty($s['enabled']) || empty($s['host'])) {
            config(self::$env);
            if (app()->resolved('mail.manager')) {
                Mail::purge('smtp');
            }
            return;
        }
        try {
            $password = $s['password'] ? Crypt::decryptString($s['password']) : null;
        } catch (\Throwable) {
            $password = null; // APP_KEY berganti → sandi harus diisi ulang
        }
        $enc = $s['encryption'] ?? 'tls';
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => array_merge(config('mail.mailers.smtp', []), [
                'transport' => 'smtp',
                'url' => null,
                'scheme' => $enc === 'ssl' ? 'smtps' : 'smtp',
                'host' => $s['host'],
                'port' => (int) $s['port'],
                'username' => $s['username'] ?: null,
                'password' => $password,
                'timeout' => 15,
                'auto_tls' => $enc !== 'none',
                'require_tls' => $enc === 'tls',
            ]),
            'mail.from.address' => $s['from_address'],
            'mail.from.name' => $s['from_name'] ?: config('manrisk.app_name'),
        ]);
        if (app()->resolved('mail.manager')) {
            Mail::purge('smtp');
        }
    }

    /** Apakah aplikasi dapat benar-benar mengirim email (bukan sekadar menulis ke log). */
    public static function ready(): bool
    {
        $s = self::stored();
        if (!empty($s['enabled']) && !empty($s['host']) && !empty($s['from_address'])) {
            return true;
        }
        $env = self::$env ?? ['mail.default' => config('mail.default'), 'mail.mailers.smtp' => config('mail.mailers.smtp')];
        $default = $env['mail.default'];
        if (in_array($default, ['log', 'array', null], true)) {
            return false;
        }
        if ($default === 'smtp') {
            $host = (string) ($env['mail.mailers.smtp']['host'] ?? '');
            return $host !== '' && !in_array($host, ['127.0.0.1', 'localhost'], true);
        }
        return true;
    }

    /** Ringkasan untuk antarmuka (tanpa sandi). */
    public static function summary(): array
    {
        $s = self::stored();
        return [
            'enabled' => (bool) ($s['enabled'] ?? false),
            'host' => $s['host'] ?? '',
            'port' => $s['port'] ?? 587,
            'encryption' => $s['encryption'] ?? 'tls',
            'username' => $s['username'] ?? '',
            'has_password' => !empty($s['password']),
            'from_address' => $s['from_address'] ?? '',
            'from_name' => $s['from_name'] ?? config('manrisk.app_name'),
            'tested_at' => $s['tested_at'] ?? null,
            'env_mailer' => (self::$env ?? ['mail.default' => config('mail.default')])['mail.default'],
            'ready' => self::ready(),
        ];
    }

    /** Apakah ada akun/kebijakan yang bergantung pada email (MFA) sehingga email tidak boleh dimatikan. */
    public static function inUseByMfa(): bool
    {
        if (User::withoutGlobalScopes()->where('mfa_enabled', true)->where('active', true)->exists()) {
            return true;
        }
        return Organization::all()->contains(fn ($o) => !empty($o->settings['mfa_required_roles'] ?? []));
    }

    public static function flush(): void
    {
        self::$cache = null;
        self::$env = null;
    }
}

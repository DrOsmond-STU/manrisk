<?php

namespace App\Support;

use App\Mail\MfaCodeMail;
use App\Mail\SecurityNoticeMail;
use App\Models\AuthLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Autentikasi multifaktor berbasis kode sekali pakai (OTP) yang dikirim lewat email/SMTP.
 * - Kode 6 digit, berlaku singkat, disimpan sebagai HMAC (bukan teks asli), maks. 5 kali coba per kode.
 * - Kode pemulihan (10 buah) untuk saat email tidak dapat diakses; disimpan sebagai HMAC, sekali pakai.
 * - Perangkat tepercaya opsional (cookie terenkripsi + token acak yang di-hash di basis data).
 */
class Mfa
{
    public const PURPOSE_LOGIN = 'login';
    public const PURPOSE_ENABLE = 'enable';
    public const TRUST_OPTIONS = [0, 7, 30];

    private const CODE_ATTEMPTS = 5;
    private const RESEND_SECONDS = 60;
    private const SENDS_PER_WINDOW = 5;
    private const RECOVERY_COUNT = 10;
    private const RECOVERY_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function ttlMinutes(): int
    {
        return max(3, (int) config('manrisk.mfa.code_ttl_minutes', 10));
    }

    /** Peran yang diwajibkan MFA oleh kebijakan organisasi pengguna. */
    public static function requiredRoles(User $user): array
    {
        return (array) ($user->organization?->settings['mfa_required_roles'] ?? []);
    }

    public static function requiredByPolicy(User $user): bool
    {
        return in_array($user->role, self::requiredRoles($user), true);
    }

    /** MFA berlaku bagi pengguna: diaktifkan sendiri atau diwajibkan kebijakan. */
    public static function active(User $user): bool
    {
        return (bool) $user->mfa_enabled || self::requiredByPolicy($user);
    }

    public static function trustDays(User $user): int
    {
        $d = (int) ($user->organization?->settings['mfa_trust_days'] ?? 0);
        return in_array($d, self::TRUST_OPTIONS, true) ? $d : 0;
    }

    /** Detik tersisa sebelum kode baru boleh dikirim (hanya bila masih ada kode aktif yang belum dipakai). */
    public static function resendWait(User $user, string $purpose): int
    {
        $last = DB::table('mfa_codes')->where('user_id', $user->id)->where('purpose', $purpose)->whereNull('consumed_at')->where('expires_at', '>', now())->max('created_at');
        if (!$last) {
            return 0;
        }
        return max(0, self::RESEND_SECONDS - (int) now()->diffInSeconds(\Illuminate\Support\Carbon::parse($last), true));
    }

    /**
     * Buat dan kirim kode baru (kode lama untuk tujuan yang sama otomatis tidak berlaku).
     *
     * @return array{ok: bool, reason?: string, wait?: int, message: string}
     */
    public static function send(User $user, string $purpose): array
    {
        if ($wait = self::resendWait($user, $purpose)) {
            return ['ok' => false, 'reason' => 'cooldown', 'wait' => $wait, 'message' => "Tunggu {$wait} detik sebelum meminta kode baru."];
        }
        $key = 'mfa-send:' . $user->id;
        if (RateLimiter::tooManyAttempts($key, self::SENDS_PER_WINDOW)) {
            $m = (int) ceil(RateLimiter::availableIn($key) / 60);
            return ['ok' => false, 'reason' => 'limit', 'wait' => RateLimiter::availableIn($key), 'message' => "Terlalu banyak permintaan kode. Coba lagi dalam {$m} menit."];
        }
        RateLimiter::hit($key, 15 * 60);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        DB::table('mfa_codes')->where('user_id', $user->id)->where('purpose', $purpose)->whereNull('consumed_at')->update(['consumed_at' => now()]);
        DB::table('mfa_codes')->insert([
            'user_id' => $user->id, 'purpose' => $purpose, 'code_hash' => self::hash($user, $code),
            'expires_at' => now()->addMinutes(self::ttlMinutes()), 'ip' => request()?->ip(), 'created_at' => now(),
        ]);
        // Bersihkan kode lama
        DB::table('mfa_codes')->where('created_at', '<', now()->subDay())->delete();

        try {
            Mail::to($user->email)->send(new MfaCodeMail($user->name, $code, $purpose, self::ttlMinutes(), (string) request()?->ip()));
        } catch (\Throwable $e) {
            report($e);
            AuthLog::write('mfa_send_failed', $user->email, $user, mb_substr(class_basename($e) . ': ' . $e->getMessage(), 0, 200));
            return ['ok' => false, 'reason' => 'mail', 'message' => 'Kode verifikasi gagal dikirim ke email. Gunakan kode pemulihan atau hubungi administrator.'];
        }
        AuthLog::write('mfa_code_sent', $user->email, $user, $purpose);
        return ['ok' => true, 'message' => 'Kode verifikasi telah dikirim ke ' . self::maskEmail($user->email) . '.'];
    }

    /** Periksa kode OTP; kode yang cocok langsung dihanguskan. */
    public static function check(User $user, string $code, string $purpose): bool
    {
        $code = preg_replace('/\D/', '', $code);
        $row = DB::table('mfa_codes')->where('user_id', $user->id)->where('purpose', $purpose)->whereNull('consumed_at')
            ->where('expires_at', '>', now())->orderByDesc('id')->first();
        if (!$row || $row->attempts >= self::CODE_ATTEMPTS || strlen($code) !== 6) {
            if ($row) {
                DB::table('mfa_codes')->where('id', $row->id)->increment('attempts');
            }
            return false;
        }
        if (!hash_equals($row->code_hash, self::hash($user, $code))) {
            DB::table('mfa_codes')->where('id', $row->id)->increment('attempts');
            return false;
        }
        // Konsumsi atomik: hanya satu permintaan yang berhasil memakai kode ini
        return DB::table('mfa_codes')->where('id', $row->id)->whereNull('consumed_at')->update(['consumed_at' => now()]) === 1;
    }

    /** Buat set kode pemulihan baru (menggantikan yang lama); kembalikan teks asli untuk ditampilkan sekali. */
    public static function newRecoveryCodes(User $user): array
    {
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_COUNT; $i++) {
            $raw = '';
            for ($j = 0; $j < 10; $j++) {
                $raw .= self::RECOVERY_ALPHABET[random_int(0, strlen(self::RECOVERY_ALPHABET) - 1)];
            }
            $codes[] = substr($raw, 0, 5) . '-' . substr($raw, 5);
        }
        $user->forceFill(['mfa_recovery_codes' => array_map(fn ($c) => self::hash($user, self::normalizeRecovery($c)), $codes)])->saveQuietly();
        return $codes;
    }

    public static function useRecoveryCode(User $user, string $code): bool
    {
        $norm = self::normalizeRecovery($code);
        if (strlen($norm) !== 10) {
            return false;
        }
        $hash = self::hash($user, $norm);
        $list = (array) ($user->mfa_recovery_codes ?? []);
        foreach ($list as $i => $h) {
            if (hash_equals((string) $h, $hash)) {
                unset($list[$i]);
                $user->forceFill(['mfa_recovery_codes' => array_values($list)])->saveQuietly();
                return true;
            }
        }
        return false;
    }

    public static function recoveryRemaining(User $user): int
    {
        return count((array) ($user->mfa_recovery_codes ?? []));
    }

    // ---- Perangkat tepercaya ----

    public static function cookieName(): string
    {
        return (config('session.secure') ? '__Host-' : '') . 'mr_trusted_device';
    }

    public static function trustDevice(User $user, Request $request, int $days): void
    {
        $token = Str::random(64);
        DB::table('mfa_trusted_devices')->insert([
            'user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            'ip' => $request->ip(), 'expires_at' => now()->addDays($days), 'created_at' => now(),
        ]);
        DB::table('mfa_trusted_devices')->where('expires_at', '<', now())->delete();
        Cookie::queue(Cookie::make(self::cookieName(), $user->id . '|' . $token, $days * 1440, '/', null, config('session.secure'), true, false, 'lax'));
        AuthLog::write('mfa_device_trusted', $user->email, $user, "days={$days}");
    }

    public static function isTrusted(User $user, Request $request): bool
    {
        if (self::trustDays($user) === 0) {
            return false; // kebijakan dimatikan → perangkat tepercaya lama tidak berlaku
        }
        $value = (string) $request->cookie(self::cookieName());
        [$uid, $token] = array_pad(explode('|', $value, 2), 2, '');
        if ((int) $uid !== $user->id || strlen($token) !== 64) {
            return false;
        }
        $row = DB::table('mfa_trusted_devices')->where('user_id', $user->id)->where('token_hash', hash('sha256', $token))->where('expires_at', '>', now())->first();
        if (!$row) {
            return false;
        }
        DB::table('mfa_trusted_devices')->where('id', $row->id)->update(['last_used_at' => now()]);
        return true;
    }

    public static function trustedCount(User $user): int
    {
        return DB::table('mfa_trusted_devices')->where('user_id', $user->id)->where('expires_at', '>', now())->count();
    }

    public static function forgetDevices(User $user): int
    {
        return DB::table('mfa_trusted_devices')->where('user_id', $user->id)->delete();
    }

    /** Nonaktifkan MFA pribadi dan cabut semua artefaknya (kode, kode pemulihan, perangkat tepercaya). */
    public static function reset(User $user): void
    {
        $user->forceFill(['mfa_enabled' => false, 'mfa_enabled_at' => null, 'mfa_recovery_codes' => null])->save();
        DB::table('mfa_codes')->where('user_id', $user->id)->delete();
        self::forgetDevices($user);
    }

    /** Kirim pemberitahuan keamanan ke pemilik akun; kegagalan kirim tidak menggagalkan aksi. */
    public static function notify(User $user, string $title, string $body): void
    {
        try {
            Mail::to($user->email)->send(new SecurityNoticeMail($user->name, $title, $body, (string) request()?->ip()));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $keep = mb_substr($local, 0, min(2, max(1, mb_strlen($local) - 1)));
        return $keep . str_repeat('•', max(1, mb_strlen($local) - mb_strlen($keep))) . '@' . $domain;
    }

    private static function hash(User $user, string $value): string
    {
        return hash_hmac('sha256', $user->id . '|' . $value, (string) config('app.key'));
    }

    private static function normalizeRecovery(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
    }
}

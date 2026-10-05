<?php

namespace App\Console\Commands;

use App\Models\AuthLog;
use App\Models\User;
use App\Support\PasswordPolicy;
use App\Support\SessionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Hardening go-live: ganti sandi bawaan seed dengan sandi acak unik per akun, wajib ganti saat masuk.
 * Daftar sandi baru ditulis ke berkas (izin 0600) di luar folder aplikasi, bukan ke log.
 */
class RotatePasswords extends Command
{
    protected $signature = 'manrisk:rotate-passwords {--only=* : Email tertentu} {--out= : Berkas keluaran} {--disable-others : Nonaktifkan login akun selain --only dengan sandi acak yang tidak dicatat}';

    protected $description = 'Ganti sandi akun dengan sandi acak sementara (wajib diganti saat masuk pertama)';

    public function handle(): int
    {
        $only = array_map('strtolower', (array) $this->option('only'));
        $out = $this->option('out') ?: storage_path('app/private/credentials-' . now()->format('YmdHis') . '.txt');
        $lines = ['# ManRisk — sandi sementara ' . now()->toDateTimeString() . ' (wajib diganti saat masuk pertama). HAPUS berkas ini setelah dibagikan.'];
        foreach (User::withoutGlobalScopes()->orderBy('id')->get() as $u) {
            $listed = !$only || in_array(strtolower($u->email), $only, true);
            if (!$listed && !$this->option('disable-others')) {
                continue;
            }
            $plain = Str::password(16, true, true, true, false);
            PasswordPolicy::apply($u, $plain, true);
            SessionManager::revokeAll($u);
            AuthLog::write('password_rotated', $u->email, $u, 'console');
            if ($listed) {
                $lines[] = str_pad($u->roleLabel(), 20) . ' ' . str_pad($u->email, 36) . ' ' . $plain;
            }
        }
        @mkdir(dirname($out), 0700, true);
        file_put_contents($out, implode(PHP_EOL, $lines) . PHP_EOL);
        @chmod($out, 0600);
        $this->info('Sandi diganti; daftar tersimpan di ' . $out);
        return self::SUCCESS;
    }
}

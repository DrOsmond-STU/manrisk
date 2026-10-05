<?php

namespace App\Console\Commands;

use App\Models\AuthLog;
use App\Models\User;
use App\Support\Mfa;
use App\Support\SessionManager;
use Illuminate\Console\Command;

/** Pemulihan darurat: reset MFA akun dari server (mis. Super Admin kehilangan akses email & kode pemulihan). */
class ResetMfa extends Command
{
    protected $signature = 'manrisk:mfa-reset {email : Email akun}';

    protected $description = 'Nonaktifkan MFA pribadi akun dan cabut kode pemulihan serta perangkat tepercayanya';

    public function handle(): int
    {
        $user = User::withoutGlobalScopes()->where('email', mb_strtolower(trim($this->argument('email'))))->first();
        if (!$user) {
            $this->error('Akun tidak ditemukan.');
            return self::FAILURE;
        }
        Mfa::reset($user);
        SessionManager::revokeAll($user);
        AuthLog::write('mfa_reset', $user->email, $user, 'by=cli');
        $this->info("MFA {$user->email} direset.");
        if (Mfa::requiredByPolicy($user)) {
            $this->warn('Peran akun ini diwajibkan MFA oleh kebijakan organisasi; kode tetap dikirim ke email saat login.');
        }
        return self::SUCCESS;
    }
}

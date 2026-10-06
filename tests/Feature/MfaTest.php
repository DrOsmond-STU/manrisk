<?php

namespace Tests\Feature;

use App\Mail\MfaCodeMail;
use App\Mail\SecurityNoticeMail;
use App\Models\User;
use App\Support\MailSettings;
use App\Support\Mfa;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class MfaTest extends TestCase
{
    private const PASS = 'Secret#Pass123';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        RateLimiter::clear('mfa-send:' . $this->users['risk_manager']->id);
        RateLimiter::clear('mfa-verify:' . $this->users['risk_manager']->id);
    }

    private function configureSmtp(): void
    {
        MailSettings::save(['enabled' => true, 'host' => 'mail.contoh.id', 'port' => 465, 'encryption' => 'ssl', 'username' => 'noreply@contoh.id', 'password' => 'RahasiaSmtp!', 'from_address' => 'noreply@contoh.id', 'from_name' => 'ManRisk']);
    }

    private function enableFor(User $u): void
    {
        $u->forceFill(['mfa_enabled' => true, 'mfa_enabled_at' => now()])->save();
    }

    /** Ambil kode OTP dari email yang dikirim (Mail::fake). */
    private function lastCode(): string
    {
        $code = null;
        Mail::assertSent(MfaCodeMail::class, function (MfaCodeMail $m) use (&$code) {
            $code = $m->code;
            return true;
        });
        return $code;
    }

    public function test_login_without_mfa_is_unchanged(): void
    {
        $this->post('/login', ['email' => 'risk_manager@uji.test', 'password' => self::PASS])->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        Mail::assertNothingSent();
    }

    public function test_mfa_login_requires_email_code(): void
    {
        $u = $this->users['risk_manager'];
        $this->enableFor($u);
        $this->post('/login', ['email' => $u->email, 'password' => self::PASS])->assertRedirect('/login/verify');
        $this->assertGuest();
        Mail::assertSent(MfaCodeMail::class, fn ($m) => $m->hasTo($u->email));
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/login/verify')->assertOk()->assertInertia(fn ($p) => $p->component('Auth/MfaVerify')->where('email', 'ri••••••••••@uji.test'));

        $code = $this->lastCode();
        $this->assertDatabaseMissing('mfa_codes', ['code_hash' => $code]); // tidak disimpan sebagai teks asli
        $this->post('/login/verify', ['code' => $code, 'mode' => 'otp'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($u);
        $this->assertDatabaseHas('auth_logs', ['event' => 'login_success', 'user_id' => $u->id, 'detail' => 'via=mfa_email']);
    }

    public function test_wrong_code_rejected_and_code_single_use(): void
    {
        $u = $this->users['risk_manager'];
        $this->enableFor($u);
        $this->post('/login', ['email' => $u->email, 'password' => self::PASS]);
        $code = $this->lastCode();
        $wrong = $code === '000000' ? '111111' : '000000';
        $this->post('/login/verify', ['code' => $wrong, 'mode' => 'otp'])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->post('/login/verify', ['code' => $code, 'mode' => 'otp'])->assertRedirect('/dashboard');
        // Kode yang sudah dipakai tidak bisa dipakai lagi
        $this->post('/logout');
        $this->post('/login', ['email' => $u->email, 'password' => self::PASS]);
        $this->post('/login/verify', ['code' => $code, 'mode' => 'otp'])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_code_locked_after_five_wrong_attempts(): void
    {
        $u = $this->users['risk_manager'];
        $this->enableFor($u);
        $this->post('/login', ['email' => $u->email, 'password' => self::PASS]);
        $code = $this->lastCode();
        $wrong = $code === '000000' ? '111111' : '000000';
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login/verify', ['code' => $wrong, 'mode' => 'otp']);
        }
        $this->post('/login/verify', ['code' => $code, 'mode' => 'otp'])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_too_many_failures_end_pending_login(): void
    {
        $u = $this->users['risk_manager'];
        $this->enableFor($u);
        $this->post('/login', ['email' => $u->email, 'password' => self::PASS]);
        for ($i = 0; $i < 10; $i++) {
            $this->post('/login/verify', ['code' => '12345' . $i, 'mode' => 'otp']);
        }
        $this->post('/login/verify', ['code' => '123456', 'mode' => 'otp'])->assertRedirect('/login');
        $this->assertDatabaseHas('auth_logs', ['event' => 'mfa_locked', 'user_id' => $u->id]);
    }

    public function test_expired_code_rejected(): void
    {
        $u = $this->users['risk_manager'];
        $this->enableFor($u);
        $this->post('/login', ['email' => $u->email, 'password' => self::PASS]);
        $code = $this->lastCode();
        $this->travel(11)->minutes();
        $this->post('/login/verify', ['code' => $code, 'mode' => 'otp'])->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_verify_without_pending_password_step_is_rejected(): void
    {
        $this->post('/login/verify', ['code' => '123456', 'mode' => 'otp'])->assertRedirect('/login');
        $this->get('/login/verify')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_resend_is_throttled(): void
    {
        $u = $this->users['risk_manager'];
        $this->enableFor($u);
        $this->post('/login', ['email' => $u->email, 'password' => self::PASS]);
        $this->post('/login/verify/resend')->assertSessionHas('error');
        Mail::assertSentCount(1);
        $this->travel(61)->seconds();
        $this->post('/login/verify/resend')->assertSessionHas('status');
        Mail::assertSentCount(2);
    }

    public function test_relogin_right_after_successful_mfa_gets_fresh_code(): void
    {
        $u = $this->users['risk_manager'];
        $this->enableFor($u);
        $this->post('/login', ['email' => $u->email, 'password' => self::PASS]);
        $this->post('/login/verify', ['code' => $this->lastCode(), 'mode' => 'otp'])->assertRedirect('/dashboard');
        $this->post('/logout');
        // Kode sebelumnya sudah dipakai → kode baru langsung dikirim meski belum 60 detik
        $this->post('/login', ['email' => $u->email, 'password' => self::PASS])->assertRedirect('/login/verify');
        Mail::assertSentCount(2);
        $codes = [];
        Mail::assertSent(MfaCodeMail::class, function ($m) use (&$codes) { $codes[] = $m->code; return true; });
        $this->post('/login/verify', ['code' => end($codes), 'mode' => 'otp'])->assertRedirect('/dashboard');
    }

    public function test_send_limit_is_reported_honestly(): void
    {
        $u = $this->users['risk_manager'];
        $this->enableFor($u);
        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue(Mfa::send($u, Mfa::PURPOSE_LOGIN)['ok']);
            $this->travel(61)->seconds();
        }
        $r = Mfa::send($u, Mfa::PURPOSE_LOGIN);
        $this->assertSame('limit', $r['reason']);
        $this->post('/login', ['email' => $u->email, 'password' => self::PASS])->assertRedirect('/login/verify')->assertSessionHas('error');
    }

    public function test_recovery_code_login_and_single_use(): void
    {
        $u = $this->users['risk_manager'];
        $this->enableFor($u);
        $codes = Mfa::newRecoveryCodes($u);
        $this->post('/login', ['email' => $u->email, 'password' => self::PASS]);
        $this->post('/login/verify', ['code' => strtolower($codes[0]), 'mode' => 'recovery'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($u);
        $this->assertSame(9, Mfa::recoveryRemaining($u->fresh()));
        Mail::assertSent(SecurityNoticeMail::class, fn ($m) => $m->hasTo($u->email));
        $this->post('/logout');
        $this->post('/login', ['email' => $u->email, 'password' => self::PASS]);
        $this->post('/login/verify', ['code' => $codes[0], 'mode' => 'recovery'])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertStringNotContainsString($codes[1], (string) DB::table('users')->where('id', $u->id)->value('mfa_recovery_codes'));
    }

    public function test_policy_requires_mfa_for_role(): void
    {
        $this->configureSmtp();
        $this->as('super_admin')->put('/settings/organization', ['name' => 'Org Uji', 'code' => 'UJI', 'settings' => ['mfa_required_roles' => ['risk_manager'], 'mfa_trust_days' => 30]])->assertSessionHas('success');
        $this->post('/logout');
        $this->post('/login', ['email' => 'risk_manager@uji.test', 'password' => self::PASS])->assertRedirect('/login/verify');
        $this->assertGuest();
        $this->post('/login', ['email' => 'auditor@uji.test', 'password' => self::PASS])->assertRedirect('/dashboard');
    }

    public function test_policy_cannot_be_enabled_without_smtp(): void
    {
        $this->as('super_admin')->put('/settings/organization', ['name' => 'Org Uji', 'code' => 'UJI', 'settings' => ['mfa_required_roles' => ['super_admin']]])->assertSessionHas('error');
        $this->assertEmpty($this->org->fresh()->settings['mfa_required_roles'] ?? []);
    }

    public function test_trusted_device_skips_code_until_password_change(): void
    {
        $this->configureSmtp();
        $this->org->update(['settings' => ['mfa_trust_days' => 30]]);
        $u = $this->users['risk_manager'];
        $this->enableFor($u);
        $this->post('/login', ['email' => $u->email, 'password' => self::PASS]);
        $r = $this->post('/login/verify', ['code' => $this->lastCode(), 'mode' => 'otp', 'trust' => true])->assertRedirect('/dashboard');
        $cookie = collect($r->headers->getCookies())->first(fn ($c) => str_contains($c->getName(), 'mr_trusted_device'));
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->post('/logout');

        $value = Crypt::decryptString($cookie->getValue());
        $plain = explode('|', $value, 2)[1]; // hilangkan prefiks HMAC cookie Laravel
        $this->withCookie($cookie->getName(), $plain)->post('/login', ['email' => $u->email, 'password' => self::PASS])->assertRedirect('/dashboard');
        $this->assertDatabaseHas('auth_logs', ['event' => 'login_success', 'detail' => 'via=trusted_device']);
        $this->post('/logout');

        // Cookie milik pengguna lain tidak berlaku
        $other = $this->users['risk_admin'];
        $this->enableFor($other);
        $this->withCookie($cookie->getName(), $plain)->post('/login', ['email' => $other->email, 'password' => self::PASS])->assertRedirect('/login/verify');
        $this->post('/login/verify/cancel');

        Mfa::forgetDevices($u);
        $this->withCookie($cookie->getName(), $plain)->post('/login', ['email' => $u->email, 'password' => self::PASS])->assertRedirect('/login/verify');
    }

    public function test_user_enables_and_disables_mfa_from_profile(): void
    {
        $this->configureSmtp();
        $u = $this->users['risk_officer'];
        $this->actingAs($u)->post('/profile/mfa/send')->assertSessionHas('success');
        $code = $this->lastCode();
        $this->actingAs($u)->post('/profile/mfa/enable', ['code' => '000000' === $code ? '111111' : '000000'])->assertSessionHasErrors('code');
        $this->actingAs($u)->post('/profile/mfa/enable', ['code' => $code])->assertSessionHas('mfa_recovery_codes');
        $this->assertTrue($u->fresh()->mfa_enabled);
        $this->assertSame(10, Mfa::recoveryRemaining($u->fresh()));
        $this->actingAs($u)->get('/profile')->assertInertia(fn ($p) => $p->where('mfa.enabled', true)->where('mfa.recovery_remaining', 10));

        $this->actingAs($u)->post('/profile/mfa/disable', ['password' => 'salah'])->assertSessionHasErrors('password');
        $this->assertTrue($u->fresh()->mfa_enabled);
        $this->actingAs($u)->post('/profile/mfa/disable', ['password' => self::PASS])->assertSessionHas('success');
        $this->assertFalse($u->fresh()->mfa_enabled);
        $this->assertNull($u->fresh()->mfa_recovery_codes);
    }

    public function test_enable_requires_smtp(): void
    {
        $this->as('risk_officer')->post('/profile/mfa/send')->assertSessionHas('error');
        Mail::assertNothingSent();
    }

    public function test_policy_required_user_cannot_disable(): void
    {
        $this->configureSmtp();
        $this->org->update(['settings' => ['mfa_required_roles' => ['risk_officer']]]);
        $u = $this->users['risk_officer'];
        $this->enableFor($u);
        $this->actingAs($u)->post('/profile/mfa/disable', ['password' => self::PASS])->assertSessionHas('error');
        $this->assertTrue($u->fresh()->mfa_enabled);
    }

    public function test_admin_can_reset_user_mfa_and_others_cannot(): void
    {
        $u = $this->users['risk_officer'];
        $this->enableFor($u);
        Mfa::newRecoveryCodes($u);
        $this->as('risk_admin')->post("/admin/users/{$u->id}/reset-mfa")->assertForbidden();
        $this->as('super_admin')->post("/admin/users/{$u->id}/reset-mfa")->assertSessionHas('success');
        $this->assertFalse($u->fresh()->mfa_enabled);
        $this->assertDatabaseHas('auth_logs', ['event' => 'mfa_reset', 'user_id' => $u->id]);
    }

    public function test_mfa_reset_cli(): void
    {
        $u = $this->users['super_admin'];
        $this->enableFor($u);
        $this->artisan('manrisk:mfa-reset', ['email' => $u->email])->assertSuccessful();
        $this->assertFalse($u->fresh()->mfa_enabled);
    }

    public function test_smtp_settings_super_admin_only_and_password_encrypted(): void
    {
        $payload = ['enabled' => true, 'host' => 'mail.contoh.id', 'port' => 587, 'encryption' => 'tls', 'username' => 'noreply@contoh.id', 'password' => 'RahasiaSmtp!', 'from_address' => 'noreply@contoh.id', 'from_name' => 'ManRisk'];
        $this->as('risk_admin')->get('/admin/mail')->assertForbidden();
        $this->as('risk_admin')->put('/admin/mail', $payload)->assertForbidden();
        $this->as('super_admin')->get('/admin/mail')->assertOk()->assertInertia(fn ($p) => $p->component('Settings/Mail')->where('mail.ready', false));
        $this->as('super_admin')->put('/admin/mail', $payload)->assertSessionHas('success');

        $raw = (string) DB::table('system_settings')->where('key', 'mail')->value('value');
        $this->assertStringNotContainsString('RahasiaSmtp!', $raw);
        MailSettings::flush();
        $this->assertTrue(MailSettings::ready());
        $this->as('super_admin')->get('/admin/mail')->assertInertia(fn ($p) => $p->where('mail.has_password', true)->missing('mail.password'));

        // Konfigurasi diterapkan ke mailer
        MailSettings::apply();
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('mail.contoh.id', config('mail.mailers.smtp.host'));
        $this->assertSame('RahasiaSmtp!', config('mail.mailers.smtp.password'));
        $this->assertTrue(config('mail.mailers.smtp.require_tls'));

        // Sandi kosong = sandi lama dipertahankan
        $this->as('super_admin')->put('/admin/mail', array_merge($payload, ['password' => '', 'port' => 465, 'encryption' => 'ssl']))->assertSessionHas('success');
        MailSettings::flush();
        MailSettings::apply();
        $this->assertSame('RahasiaSmtp!', config('mail.mailers.smtp.password'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));

        // Host tidak valid ditolak
        $this->as('super_admin')->put('/admin/mail', array_merge($payload, ['host' => 'mail.contoh.id/../x']))->assertSessionHasErrors('host');
    }

    public function test_smtp_test_mail_and_cannot_disable_while_mfa_in_use(): void
    {
        $this->configureSmtp();
        $this->as('super_admin')->post('/admin/mail/test', ['to' => 'uji@contoh.id'])->assertSessionHas('success');
        Mail::assertSent(SecurityNoticeMail::class, fn ($m) => $m->hasTo('uji@contoh.id'));
        $this->enableFor($this->users['risk_manager']);
        $this->as('super_admin')->put('/admin/mail', ['enabled' => false])->assertSessionHas('error');
        MailSettings::flush();
        $this->assertTrue(MailSettings::ready());
    }

    public function test_password_change_forgets_trusted_devices(): void
    {
        $u = $this->users['risk_manager'];
        DB::table('mfa_trusted_devices')->insert(['user_id' => $u->id, 'token_hash' => hash('sha256', 'x'), 'expires_at' => now()->addDays(30), 'created_at' => now()]);
        $this->actingAs($u)->put('/password', ['current_password' => self::PASS, 'password' => 'NewSecret#Pass456', 'password_confirmation' => 'NewSecret#Pass456'])->assertRedirect('/dashboard');
        $this->assertSame(0, Mfa::trustedCount($u));
    }
}

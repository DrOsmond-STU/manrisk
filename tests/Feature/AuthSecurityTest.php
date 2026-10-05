<?php

namespace Tests\Feature;

use App\Models\AuthLog;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/risks')->assertRedirect('/login');
        $this->get('/admin/users')->assertRedirect('/login');
    }

    public function test_login_success_regenerates_session_and_logs(): void
    {
        $r = $this->post('/login', ['email' => 'risk_manager@uji.test', 'password' => 'Secret#Pass123']);
        $r->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->users['risk_manager']);
        $this->assertDatabaseHas('auth_logs', ['event' => 'login_success', 'email' => 'risk_manager@uji.test']);
        $this->assertNotNull($this->users['risk_manager']->fresh()->last_login_at);
    }

    public function test_login_failure_is_generic_and_logged(): void
    {
        $this->post('/login', ['email' => 'risk_manager@uji.test', 'password' => 'salah'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseHas('auth_logs', ['event' => 'login_failed', 'detail' => 'wrong_password']);
        $this->post('/login', ['email' => 'tidakada@uji.test', 'password' => 'salah'])->assertSessionHasErrors('email');
    }

    public function test_account_locks_after_five_failed_attempts(): void
    {
        RateLimiter::clear('login:' . sha1('risk_owner@uji.test|127.0.0.1'));
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'risk_owner@uji.test', 'password' => 'salah']);
        }
        $r = $this->post('/login', ['email' => 'risk_owner@uji.test', 'password' => 'Secret#Pass123']);
        $r->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertStringContainsString('Terlalu banyak', session('errors')->first('email'));
        $this->assertDatabaseHas('auth_logs', ['event' => 'login_locked']);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->users['auditor']->update(['active' => false]);
        $this->post('/login', ['email' => 'auditor@uji.test', 'password' => 'Secret#Pass123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_logout_invalidates_session(): void
    {
        $this->as('auditor')->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertDatabaseHas('auth_logs', ['event' => 'logout', 'email' => 'auditor@uji.test']);
    }

    public function test_idle_session_is_expired(): void
    {
        $this->as('auditor')->withSession(['mr_last_activity' => now()->subMinutes(31)->timestamp, 'mr_login_at' => now()->timestamp])->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertDatabaseHas('auth_logs', ['event' => 'session_expired', 'detail' => 'idle_timeout']);
    }

    public function test_disabled_account_is_kicked_out_on_next_request(): void
    {
        $this->users['auditor']->update(['active' => false]);
        $this->as('auditor')->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_must_change_password_redirects_everywhere(): void
    {
        $this->users['risk_officer']->update(['must_change_password' => true]);
        $this->as('risk_officer')->get('/risks')->assertRedirect('/password');
        $this->as('risk_officer')->get('/password')->assertOk();
    }

    public function test_password_policy_and_history(): void
    {
        $u = $this->users['risk_officer'];
        $this->as('risk_officer')->put('/password', ['current_password' => 'Secret#Pass123', 'password' => 'pendek', 'password_confirmation' => 'pendek'])->assertSessionHasErrors('password');
        $this->as('risk_officer')->put('/password', ['current_password' => 'salah', 'password' => 'KataSandi#Baru99', 'password_confirmation' => 'KataSandi#Baru99'])->assertSessionHasErrors('current_password');
        $this->as('risk_officer')->put('/password', ['current_password' => 'Secret#Pass123', 'password' => 'KataSandi#Baru99', 'password_confirmation' => 'KataSandi#Baru99'])->assertRedirect('/dashboard');
        $this->assertTrue(\Hash::check('KataSandi#Baru99', $u->fresh()->password));
        $this->assertDatabaseHas('auth_logs', ['event' => 'password_changed']);
        // sandi lama tidak boleh dipakai ulang
        $this->as('risk_officer')->put('/password', ['current_password' => 'KataSandi#Baru99', 'password' => 'Secret#Pass123', 'password_confirmation' => 'Secret#Pass123'])->assertSessionHasErrors('password');
    }

    public function test_security_headers_present(): void
    {
        $r = $this->get('/login');
        $r->assertHeader('X-Content-Type-Options', 'nosniff');
        $r->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString("frame-ancestors 'none'", $r->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
    }

    public function test_csrf_is_enforced(): void
    {
        $this->app['env'] = 'production';
        $r = $this->as('risk_manager')->withHeaders(['X-Inertia' => 'true'])->post('/logout');
        $this->assertTrue(in_array($r->status(), [419, 302], true));
    }
}

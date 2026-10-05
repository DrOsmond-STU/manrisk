<?php

namespace Tests\Feature;

use App\Exports\ReportExport;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Review;
use App\Models\User;
use App\Services\AlertService;
use App\Services\ApprovalService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    public function test_spoofed_forwarded_for_does_not_bypass_account_lockout(): void
    {
        RateLimiter::clear('login-email:' . sha1('auditor@uji.test'));
        for ($i = 0; $i < 10; $i++) {
            $this->withHeader('X-Forwarded-For', "203.0.113.$i")->post('/login', ['email' => 'auditor@uji.test', 'password' => 'salah']);
        }
        $this->withHeader('X-Forwarded-For', '198.51.100.77')->post('/login', ['email' => 'auditor@uji.test', 'password' => 'Secret#Pass123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_public_client_cannot_spoof_ip(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->withHeader('X-Forwarded-For', '1.2.3.4')
            ->post('/login', ['email' => 'auditor@uji.test', 'password' => 'salah']);
        $this->assertDatabaseHas('auth_logs', ['event' => 'login_failed', 'ip' => '203.0.113.9']);
    }

    public function test_remember_me_is_not_honoured(): void
    {
        $r = $this->post('/login', ['email' => 'auditor@uji.test', 'password' => 'Secret#Pass123', 'remember' => true]);
        $r->assertRedirect('/dashboard');
        foreach ($r->headers->getCookies() as $c) {
            $this->assertStringNotContainsString('remember_web', $c->getName());
        }
    }

    public function test_csp_has_no_inline_scripts_and_extra_headers(): void
    {
        $r = $this->get('/login');
        $csp = $r->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression("/script-src 'self';/", $csp . ';');
        $this->assertStringContainsString("object-src 'none'", $csp);
        $r->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $r->assertHeader('X-Permitted-Cross-Domain-Policies', 'none');
        $this->assertFalse($r->headers->has('X-Powered-By'));
    }

    public function test_approver_cannot_decide_outside_unit_scope(): void
    {
        $riskB = $this->makeRisk(['status' => 'draft', 'residual_l' => 2, 'residual_i' => 2], $this->unitB);
        $a = app(ApprovalService::class)->submit($riskB, 'new_risk', $this->users['risk_officer']);
        $this->assertSame('risk_owner', $a->steps->first()->role);
        $this->as('risk_owner')->post("/approvals/{$a->id}/decide", ['action' => 'approve'])->assertForbidden();
        $this->as('risk_owner')->get('/approvals')->assertInertia(fn ($p) => $p->has('inbox', 0)->has('requested', 0));
        $this->assertDatabaseHas('auth_logs', ['event' => 'access_denied', 'user_id' => $this->users['risk_owner']->id]);
    }

    public function test_alerts_reviews_documents_do_not_leak_across_units(): void
    {
        Storage::fake('local');
        $riskB = $this->makeRisk([], $this->unitB);
        $riskA = $this->makeRisk([], $this->unitA);
        $this->actingAs($this->users['risk_manager']);
        $alertB = app(AlertService::class)->raise('t', 'info', $riskB, 'Rahasia unit B');
        app(AlertService::class)->raise('t', 'info', $riskA, 'Unit A');
        Review::create(['risk_id' => $riskB->id, 'period_type' => 'adhoc', 'period' => 'x', 'current_score' => 9, 'trend' => 'flat', 'decision' => 'continue']);
        $this->post('/documents', ['file' => UploadedFile::fake()->createWithContent('b.pdf', "%PDF-1.4\n%%EOF"), 'title' => 'Dok B', 'type' => 'evidence', 'subject_kind' => 'risk', 'subject_id' => $riskB->id]);
        $docB = Document::withoutGlobalScopes()->first();

        $this->as('risk_officer')->get('/alerts')->assertInertia(fn ($p) => $p->where('alerts.total', 1));
        $this->as('risk_officer')->post("/alerts/{$alertB->id}/read")->assertForbidden();
        $this->as('risk_officer')->get('/reviews')->assertInertia(fn ($p) => $p->where('reviews.total', 0));
        $this->as('risk_officer')->get('/documents')->assertInertia(fn ($p) => $p->where('documents.total', 0));
        $this->as('risk_officer')->delete("/documents/{$docB->id}")->assertForbidden();
        $this->as('risk_officer')->get('/dashboard')->assertInertia(fn ($p) => $p->where('kpi.total', 1));
        $this->as('risk_manager')->get('/alerts')->assertInertia(fn ($p) => $p->where('alerts.total', 2));
    }

    public function test_document_delete_only_by_uploader_or_admin(): void
    {
        Storage::fake('local');
        $risk = $this->makeRisk();
        $this->as('risk_owner')->post('/documents', ['file' => UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\n%%EOF"), 'title' => 'A', 'type' => 'evidence', 'subject_kind' => 'risk', 'subject_id' => $risk->id]);
        $d = Document::withoutGlobalScopes()->first();
        $this->as('risk_officer')->delete("/documents/{$d->id}")->assertForbidden();
        $this->as('risk_manager')->delete("/documents/{$d->id}")->assertSessionHas('success');
    }

    public function test_audit_trail_is_tenant_isolated(): void
    {
        AuditLog::create(['organization_id' => null, 'action' => 'created', 'subject_type' => 'risk', 'subject_id' => 999, 'subject_label' => 'TENANT-LAIN', 'created_at' => now()]);
        $this->as('auditor')->get('/admin/audit?q=TENANT-LAIN')->assertInertia(fn ($p) => $p->where('logs.total', 0));
    }

    public function test_spreadsheet_formula_injection_is_neutralised(): void
    {
        $this->assertSame("'=HYPERLINK(\"http://x\")", ReportExport::safe('=HYPERLINK("http://x")'));
        $this->assertSame("'@SUM(1)", ReportExport::safe('@SUM(1)'));
        $this->assertSame('Normal', ReportExport::safe('Normal'));
        $this->assertSame(-5, ReportExport::safe(-5));
    }

    public function test_logout_other_devices_and_session_revocation(): void
    {
        config(['session.driver' => 'database']);
        $u = $this->users['risk_manager'];
        foreach (['s1', 's2'] as $id) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $u->id, 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);
        }
        $this->as('risk_manager')->post('/logout-others')->assertSessionHas('success');
        $this->assertSame(0, DB::table('sessions')->whereIn('id', ['s1', 's2'])->count());
        DB::table('sessions')->insert(['id' => 's3', 'user_id' => $u->id, 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);
        $this->as('super_admin')->post("/admin/users/{$u->id}/toggle");
        $this->assertSame(0, DB::table('sessions')->where('id', 's3')->count());
    }

    public function test_last_super_admin_is_protected_and_email_globally_unique(): void
    {
        $other = User::withoutGlobalScopes()->create(['organization_id' => $this->org->id, 'name' => 'SA2', 'email' => 'sa2@uji.test', 'password' => 'Secret#Pass123', 'role' => 'super_admin']);
        $this->actingAs($other)->post("/admin/users/{$this->users['super_admin']->id}/toggle")->assertSessionHas('success');
        $this->actingAs($other)->put("/admin/users/{$other->id}", ['name' => 'SA2', 'email' => 'sa2@uji.test', 'role' => 'auditor'])->assertSessionHas('error');
        $this->actingAs($other)->post('/admin/users', ['name' => 'X', 'email' => 'AUDITOR@uji.test', 'role' => 'auditor'])->assertSessionHasErrors('email');
    }

    public function test_user_who_logged_in_cannot_be_deleted(): void
    {
        $this->users['auditor']->forceFill(['last_login_at' => now()])->save();
        $this->as('super_admin')->delete("/admin/users/{$this->users['auditor']->id}")->assertSessionHas('error');
        $this->assertNotSoftDeleted('users', ['id' => $this->users['auditor']->id]);
    }

    public function test_array_inputs_are_whitelisted(): void
    {
        $this->as('auditor')->put('/profile', ['name' => 'A', 'preferences' => ['notify' => ['mail'], 'role' => 'super_admin']])->assertSessionHasErrors('preferences');
        $this->as('super_admin')->put('/settings/organization', ['name' => 'O', 'code' => 'UJI', 'settings' => ['evil' => 1]])->assertSessionHasErrors('settings');
    }

    public function test_mass_assignment_cannot_move_data_to_other_tenant(): void
    {
        $other = \App\Models\Organization::create(['name' => 'Lain', 'code' => 'LAIN']);
        $this->as('risk_manager')->post('/organization/objectives', ['code' => 'SS-X', 'name' => 'x', 'organization_id' => $other->id]);
        $this->assertDatabaseHas('objectives', ['code' => 'SS-X', 'organization_id' => $this->org->id]);
    }

    public function test_unknown_and_known_email_get_same_response(): void
    {
        $a = $this->post('/login', ['email' => 'tidakada@uji.test', 'password' => 'x']);
        $a->assertSessionHasErrors(['email' => 'Email atau kata sandi salah.']);
        $b = $this->post('/login', ['email' => 'auditor@uji.test', 'password' => 'x']);
        $b->assertSessionHasErrors(['email' => 'Email atau kata sandi salah.']);
        $this->assertSame($a->status(), $b->status());
    }
}

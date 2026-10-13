<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\Kri;
use App\Models\RiskQuarterTarget;
use App\Models\RmiAssessment;
use App\Models\RmiParameter;
use App\Models\Scope;
use App\Services\ApprovalService;

/** Paginasi daftar data dan perbaikan temuan tinjauan keamanan modul BUMN. */
class PaginationSecurityTest extends \Tests\TestCase
{
    public function test_list_pages_are_server_paginated(): void
    {
        $risk = $this->makeRisk();
        $this->actingAs($this->users['risk_manager']);
        for ($i = 1; $i <= 14; $i++) {
            Kri::create(['code' => "KRI-$i", 'name' => "K$i", 'risk_id' => $risk->id, 'source' => 'manual', 'frequency' => 'monthly', 'direction' => 'up_bad', 'threshold_warn' => 1, 'threshold_crit' => 2]);
        }
        // KRI: 12 per halaman, statistik tetap dari seluruh data
        $this->get('/kris')->assertInertia(fn ($p) => $p->has('kris.data', 12)->where('kris.total', 14)->where('kris.last_page', 2)->where('stats.total', 14));
        $this->get('/kris?page=2')->assertInertia(fn ($p) => $p->has('kris.data', 2)->where('kris.current_page', 2));
        // halaman di luar rentang dikembalikan ke halaman terakhir, bukan daftar kosong
        $this->get('/kris?page=99')->assertInertia(fn ($p) => $p->where('kris.current_page', 2));
        $paged = ['/action-plans' => 'plans', '/controls' => 'controls', '/incidents/losses' => 'losses', '/improvements' => 'items', '/rmi' => 'assessments', '/risks' => 'risks', '/incidents' => 'incidents', '/reviews' => 'reviews', '/documents' => 'documents', '/alerts' => 'alerts', '/admin/audit' => 'logs'];
        foreach ($paged as $url => $prop) {
            $this->as('super_admin')->get($url)->assertOk()->assertInertia(fn ($p) => $p->has("$prop.data")->has("$prop.total")->has("$prop.links"));
        }
        $this->as('super_admin')->get('/context')->assertInertia(fn ($p) => $p->has('consultations.data'));
        $this->as('super_admin')->get('/reports')->assertInertia(fn ($p) => $p->has('jobs.data')->has('schedules.data'));
        $this->as('super_admin')->get('/admin/users')->assertInertia(fn ($p) => $p->has('users.data')->has('auth_logs.data'));
        $this->as('super_admin')->get('/improvements')->assertInertia(fn ($p) => $p->has('lessons.data'));
    }

    public function test_kks_pdf_and_counts_respect_unit_scope(): void
    {
        $this->actingAs($this->users['risk_manager']);
        $kks = Scope::create(['name' => 'Akuisisi', 'kind' => 'kks', 'kks_type' => 'acquisition']);
        $this->makeRisk(['name' => 'Risiko unit A', 'scope_id' => $kks->id]);
        $this->makeRisk(['name' => 'RAHASIA UNIT B', 'scope_id' => $kks->id], $this->unitB);
        $this->as('risk_officer')->get('/context')->assertInertia(fn ($p) => $p->where('scopes.0.risks_count', 1));
        $this->as('risk_manager')->get('/context')->assertInertia(fn ($p) => $p->where('scopes.0.risks_count', 2));
        // tangkap data yang benar-benar dikirim controller ke template PDF
        $seen = null;
        \Illuminate\Support\Facades\View::creator('reports.kks', function ($v) use (&$seen) { $seen = $v->getData()['risks']->pluck('name')->all(); });
        $this->as('risk_officer')->get("/context/scopes/{$kks->id}/kks-pdf")->assertOk();
        $this->assertSame(['Risiko unit A'], $seen);
        $this->as('risk_manager')->get("/context/scopes/{$kks->id}/kks-pdf")->assertOk();
        $this->assertCount(2, $seen);
    }

    public function test_quarter_targets_need_change_score_right_are_audited_and_restored_on_reject(): void
    {
        $risk = $this->makeRisk(['inherent_l' => 4, 'inherent_i' => 5, 'residual_l' => 3, 'residual_i' => 4, 'target_l' => 2, 'target_i' => 3]);
        $base = $this->riskPayload();
        // Risk Owner boleh mengubah uraian, tetapi tidak boleh mengubah target triwulan (hak changeScore)
        $this->as('risk_owner')->put("/risks/{$risk->id}", $base + ['quarter_targets' => [['quarter' => 1, 'target_l' => 5, 'target_i' => 5]]])->assertForbidden();
        $this->assertSame(0, RiskQuarterTarget::where('risk_id', $risk->id)->count());
        $this->as('risk_owner')->put("/risks/{$risk->id}", ['name' => 'Uraian baru'] + $base + ['quarter_targets' => []])->assertSessionHas('success');
        $this->assertSame('monitoring', $risk->fresh()->status);
        // Risk Officer: perubahan target → versi baru, persetujuan, dan jejak audit
        $this->as('risk_officer')->put("/risks/{$risk->id}", $base + ['quarter_targets' => [['quarter' => 1, 'target_l' => 2, 'target_i' => 2, 'target_exposure' => 1000]]])->assertSessionHas('success');
        $this->assertSame(1, RiskQuarterTarget::where('risk_id', $risk->id)->count());
        $this->assertTrue(AuditLog::withoutGlobalScopes()->where('subject_id', $risk->id)->where('context', 'risks.quarter_targets')->exists());
        $approval = Approval::withoutGlobalScopes()->where('subject_id', $risk->id)->where('type', 'score_change')->where('status', 'pending')->firstOrFail();
        $this->assertSame([], $approval->payload['restore_targets']);
        // penolakan mengembalikan target ke kondisi sebelum pengajuan
        app(ApprovalService::class)->decide($approval, $this->users['super_admin'], 'reject', 'tidak sesuai RKAP');
        $this->assertSame(0, RiskQuarterTarget::where('risk_id', $risk->id)->count());
    }

    public function test_rmi_score_changes_are_audited_and_protected_fields_not_mass_assignable(): void
    {
        $this->actingAs($this->users['risk_admin']);
        RmiParameter::create(['dimension' => 'culture', 'code' => 'BR-1', 'name' => 'P', 'weight' => 1]);
        $this->as('risk_admin')->post('/rmi', ['year' => now()->year, 'type' => 'internal', 'status' => 'final', 'score' => 5])->assertRedirect();
        $a = RmiAssessment::withoutGlobalScopes()->latest('id')->first();
        $this->assertSame('draft', $a->status);
        $this->assertNull($a->score);
        $s = $a->scores()->first();
        $this->as('risk_admin')->put("/rmi/{$a->id}/scores", ['scores' => [['id' => $s->id, 'score' => 4, 'evidence' => 'notulen']]])->assertSessionHas('success');
        $log = AuditLog::withoutGlobalScopes()->where('action', 'scored')->where('subject_id', $a->id)->first();
        $this->assertSame(['BR-1' => [null, 4]], $log->changes);
        // simpan ulang tanpa perubahan tidak menambah log
        $this->as('risk_admin')->put("/rmi/{$a->id}/scores", ['scores' => [['id' => $s->id, 'score' => 4, 'evidence' => 'notulen']]]);
        $this->assertSame(1, AuditLog::withoutGlobalScopes()->where('action', 'scored')->where('subject_id', $a->id)->count());
        $this->as('risk_admin')->post('/rmi-parameters', ['dimension' => 'culture', 'code' => 'BR-2', 'name' => 'Q', 'weight' => 1, 'sort' => 99999999])->assertSessionHasErrors('sort');
    }

    public function test_alert_for_deleted_subject_returns_to_list_instead_of_404(): void
    {
        $this->actingAs($this->users['risk_manager']);
        $inc = \App\Models\Incident::create(['code' => 'INC-X', 'title' => 'Hapus saya', 'occurred_at' => now(), 'status' => 'reported', 'unit_id' => $this->unitA->id]);
        $alert = \App\Models\Alert::create(['type' => 'incident', 'severity' => 'warning', 'title' => 'A', 'subject_type' => 'incident', 'subject_id' => $inc->id, 'link' => "/incidents/{$inc->id}"]);
        $this->as('risk_manager')->get("/alerts/{$alert->id}/open")->assertRedirect("/incidents/{$inc->id}");
        $inc->delete();
        $this->as('risk_manager')->get("/alerts/{$alert->id}/open")->assertRedirect('/alerts')->assertSessionHas('warning');
    }

    public function test_audit_trail_does_not_link_deleted_objects(): void
    {
        $this->actingAs($this->users['risk_manager']);
        $inc = \App\Models\Incident::create(['code' => 'INC-Y', 'title' => 'Y', 'occurred_at' => now(), 'status' => 'reported', 'unit_id' => $this->unitA->id]);
        $alive = $this->makeRisk();
        $inc->delete();
        $this->as('super_admin')->get('/admin/audit')->assertInertia(fn ($p) => $p
            ->where('logs.data', function ($rows) use ($inc, $alive) {
                $gone = collect($rows)->where('kind', 'incident')->where('subject_id', $inc->id);
                $live = collect($rows)->where('kind', 'risk')->where('subject_id', $alive->id);
                return $gone->count() >= 2 && $gone->every(fn ($r) => $r['live'] === false) && $live->isNotEmpty() && $live->every(fn ($r) => $r['live'] === true);
            }));
    }

    public function test_dashboard_recent_activity_does_not_link_deleted_objects(): void
    {
        $this->actingAs($this->users['risk_manager']);
        $inc = \App\Models\Incident::create(['code' => 'INC-Z', 'title' => 'Z', 'occurred_at' => now(), 'status' => 'reported', 'unit_id' => $this->unitA->id]);
        $inc->delete();
        $this->as('risk_manager')->get('/dashboard')->assertInertia(fn ($p) => $p->where('recent', fn ($rows) => ($r = collect($rows)->firstWhere('subject_id', $inc->id)) && $r['live'] === false));
    }
}

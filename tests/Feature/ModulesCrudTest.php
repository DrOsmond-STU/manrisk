<?php

namespace Tests\Feature;

use App\Models\ActionPlan;
use App\Models\Control;
use App\Models\Document;
use App\Models\Incident;
use App\Models\Kri;
use App\Models\OrgUnit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ModulesCrudTest extends TestCase
{
    public function test_org_units_objectives_programs_processes(): void
    {
        $this->as('risk_admin')->post('/organization/units', ['name' => 'Bagian X', 'code' => 'X1', 'type' => 'division', 'parent_id' => $this->unitA->id])->assertSessionHas('success');
        $u = OrgUnit::withoutGlobalScopes()->where('code', 'X1')->first();
        $this->assertSame(1, $u->level);
        $this->assertSame("/{$this->unitA->id}/{$u->id}/", $u->path);
        $this->as('risk_admin')->put("/organization/units/{$this->unitA->id}", ['name' => 'Unit A', 'code' => 'A', 'type' => 'bureau', 'parent_id' => $u->id])->assertSessionHasErrors('parent_id');
        $this->as('risk_admin')->delete("/organization/units/{$this->unitA->id}")->assertSessionHas('error'); // punya sub-unit
        $this->as('risk_admin')->delete("/organization/units/{$u->id}")->assertSessionHas('success');
        $this->as('risk_officer')->post('/organization/units', ['name' => 'N', 'code' => 'N', 'type' => 'unit'])->assertForbidden();

        $this->as('risk_manager')->post('/organization/objectives', ['code' => 'SS-9', 'name' => 'Sasaran', 'kpi' => 'IK'])->assertSessionHas('success');
        $o = \App\Models\Objective::withoutGlobalScopes()->first();
        $this->as('risk_manager')->put("/organization/objectives/{$o->id}", ['code' => 'SS-9', 'name' => 'Sasaran 2'])->assertSessionHas('success');
        $this->as('risk_manager')->post('/organization/programs', ['name' => 'Program', 'objective_id' => $o->id, 'budget' => 1000])->assertSessionHas('success');
        $p = \App\Models\Program::withoutGlobalScopes()->first();
        $this->as('risk_manager')->post('/organization/processes', ['name' => 'Proses', 'program_id' => $p->id, 'unit_id' => $this->unitA->id])->assertSessionHas('success');
        $pr = \App\Models\Process::withoutGlobalScopes()->first();
        $this->as('risk_manager')->delete("/organization/processes/{$pr->id}")->assertForbidden(); // hanya admin
        $this->as('risk_admin')->delete("/organization/processes/{$pr->id}")->assertSessionHas('success');
        $this->as('risk_admin')->delete("/organization/programs/{$p->id}")->assertSessionHas('success');
        $this->as('risk_admin')->delete("/organization/objectives/{$o->id}")->assertSessionHas('success');
        $this->as('risk_admin')->get('/organization/objectives')->assertOk();
    }

    public function test_context_scope_factor_consultation(): void
    {
        $this->as('risk_manager')->post('/context/scopes', ['name' => 'Lingkup 2026', 'period' => '2026'])->assertSessionHas('success');
        $s = \App\Models\Scope::withoutGlobalScopes()->first();
        $this->as('risk_manager')->post('/context/factors', ['scope_id' => $s->id, 'kind' => 'internal', 'factor' => 'SDM', 'condition' => 'kurang', 'nature' => 'weakness'])->assertSessionHas('success');
        $this->as('risk_manager')->post('/context/factors', ['kind' => 'salah', 'factor' => 'x', 'condition' => 'y', 'nature' => 'z'])->assertSessionHasErrors(['kind', 'nature']);
        $f = \App\Models\ContextFactor::withoutGlobalScopes()->first();
        $this->as('risk_manager')->put("/context/factors/{$f->id}", ['scope_id' => $s->id, 'kind' => 'external', 'factor' => 'Regulasi', 'condition' => 'baru', 'nature' => 'threat'])->assertSessionHas('success');
        $this->as('risk_manager')->post('/context/consultations', ['title' => 'Rapat', 'held_on' => '2026-01-10', 'status' => 'done'])->assertSessionHas('success');
        $c = \App\Models\Consultation::withoutGlobalScopes()->first();
        $this->as('risk_admin')->delete("/context/consultations/{$c->id}")->assertSessionHas('success');
        $this->as('risk_admin')->delete("/context/factors/{$f->id}")->assertSessionHas('success');
        $this->as('risk_admin')->delete("/context/scopes/{$s->id}")->assertSessionHas('success');
        $this->as('auditor')->get('/context')->assertOk();
    }

    public function test_criteria_versions_and_categories_recalculate(): void
    {
        $risk = $this->makeRisk(['residual_l' => 3, 'residual_i' => 3]); // 9
        $cat = $risk->category;
        $this->assertSame('monitor', $risk->evaluation); // appetite 8 tol 12 (Strategis): 9 > 8
        $this->as('risk_manager')->put("/criteria/categories/{$cat->id}", ['name' => $cat->name, 'appetite' => 4, 'tolerance' => 6])->assertSessionHas('success');
        $this->assertSame('treat', $risk->fresh()->evaluation);
        $this->as('risk_manager')->put("/criteria/categories/{$cat->id}", ['name' => $cat->name, 'appetite' => 10, 'tolerance' => 6])->assertSessionHasErrors('tolerance');
        $this->as('risk_manager')->post('/criteria/categories', ['name' => 'Baru', 'appetite' => 5, 'tolerance' => 8])->assertSessionHas('success');
        $new = \App\Models\RiskCategory::withoutGlobalScopes()->where('name', 'Baru')->first();
        $this->as('risk_admin')->delete("/criteria/categories/{$new->id}")->assertSessionHas('success');
        $this->as('risk_admin')->delete("/criteria/categories/{$cat->id}")->assertSessionHas('error'); // dipakai risiko

        $v = \App\Models\CriteriaVersion::withoutGlobalScopes()->first();
        $matrix = $v->matrix;
        $matrix['3-3'] = 'high';
        $payload = ['effective_from' => '2026-07-01', 'likelihood' => $v->likelihood, 'impact' => $v->impact, 'dimensions' => $v->dimensions, 'matrix' => $matrix, 'thresholds' => ['escalate' => 15, 'critical' => 20], 'activate' => true];
        $this->as('risk_manager')->post('/criteria', $payload)->assertSessionHas('success');
        $this->assertSame('high', $risk->fresh()->residual_level);
        $this->assertSame(2, \App\Models\CriteriaVersion::withoutGlobalScopes()->where('active', true)->value('version'));
        $this->as('risk_manager')->post("/criteria/{$v->id}/activate")->assertSessionHas('success');
        $this->assertSame('medium', $risk->fresh()->residual_level);
        $this->as('risk_officer')->post('/criteria', $payload)->assertForbidden();
    }

    public function test_controls_crud_and_assessment(): void
    {
        $risk = $this->makeRisk();
        $this->as('risk_officer')->post('/controls', ['name' => 'Backup', 'type' => 'preventive', 'mode' => 'automated', 'frequency' => 'daily', 'risk_ids' => [$risk->id]])->assertSessionHas('success');
        $c = Control::withoutGlobalScopes()->first();
        $this->assertSame('C-' . now()->year . '-001', $c->code);
        $this->assertSame(1, $c->risks()->count());
        $this->as('risk_officer')->put("/controls/{$c->id}", ['name' => 'Backup harian', 'type' => 'detective', 'mode' => 'manual', 'frequency' => 'monthly', 'risk_ids' => []])->assertSessionHas('success');
        $this->assertSame(0, $c->fresh()->risks()->count());
        $this->as('risk_officer')->post("/controls/{$c->id}/assess", ['tested_at' => now()->toDateString(), 'design_eff' => 3, 'operating_eff' => 2, 'note' => 'ok'])->assertSessionHas('success');
        $c->refresh();
        $this->assertSame(2, $c->operating_eff);
        $this->assertSame(now()->addMonth()->toDateString(), $c->next_test_at->toDateString());
        $this->as('risk_officer')->post("/controls/{$c->id}/assess", ['tested_at' => now()->addDay()->toDateString(), 'design_eff' => 9, 'operating_eff' => 2])->assertSessionHasErrors(['tested_at', 'design_eff']);
        $this->as('auditor')->get("/controls/{$c->id}")->assertOk();
        $this->as('auditor')->post("/controls/{$c->id}/assess", ['tested_at' => now()->toDateString(), 'design_eff' => 3, 'operating_eff' => 3])->assertForbidden();
        $this->as('risk_officer')->delete("/controls/{$c->id}")->assertForbidden();
        $this->as('risk_manager')->delete("/controls/{$c->id}")->assertSessionHas('success');
        $this->assertSoftDeleted('controls', ['id' => $c->id]);
    }

    public function test_action_plans_progress_cancel_and_risk_status_sync(): void
    {
        Storage::fake('local');
        $risk = $this->makeRisk(['status' => 'monitoring', 'residual_l' => 2, 'residual_i' => 3]);
        $this->as('risk_owner')->post('/action-plans', ['risk_id' => $risk->id, 'title' => 'Mitigasi 1', 'priority' => 'high', 'due_date' => now()->addMonth()->toDateString(), 'expected_dl' => 1])->assertSessionHas('success');
        $p = ActionPlan::withoutGlobalScopes()->first();
        $this->assertSame('AP-' . now()->year . '-001', $p->code);
        $this->assertSame('treating', $risk->fresh()->status);
        $this->as('risk_owner')->get("/risks/{$risk->id}")->assertInertia(fn ($x) => $x->where('projected.l', 1));
        $this->as('risk_owner')->post("/action-plans/{$p->id}/progress", ['progress' => 150])->assertSessionHasErrors('progress');
        $this->as('risk_owner')->post("/action-plans/{$p->id}/progress", ['progress' => 40, 'note' => 'jalan'])->assertSessionHas('success');
        $this->assertDatabaseHas('action_progress', ['action_plan_id' => $p->id, 'from_pct' => 0, 'to_pct' => 40]);
        $this->as('risk_owner')->post("/action-plans/{$p->id}/progress", ['progress' => 100])->assertSessionHasErrors(['note', 'evidence']);
        $this->as('risk_owner')->post("/action-plans/{$p->id}/progress", ['progress' => 100, 'note' => 'selesai', 'evidence' => UploadedFile::fake()->createWithContent('bukti.pdf', "%PDF-1.4\n%%EOF")])->assertSessionHas('success');
        $this->assertDatabaseHas('documents', ['subject_type' => 'action_plan', 'subject_id' => $p->id]);
        $this->assertNotNull($p->fresh()->verified_at); // pemilik risiko = verifikasi otomatis
        $this->assertNotNull($p->fresh()->completed_at);
        $this->assertSame('monitoring', $risk->fresh()->status);
        $this->as('risk_owner')->put("/action-plans/{$p->id}", ['risk_id' => $risk->id, 'title' => 'Diubah', 'priority' => 'low', 'due_date' => now()->addMonths(2)->toDateString()])->assertSessionHas('success');
        $this->as('risk_owner')->post('/action-plans', ['risk_id' => $risk->id, 'title' => 'Mitigasi 2', 'priority' => 'low', 'due_date' => now()->addMonth()->toDateString()]);
        $p2 = ActionPlan::withoutGlobalScopes()->orderByDesc('id')->first();
        $this->as('risk_owner')->post("/action-plans/{$p2->id}/cancel", ['reason' => 'tidak perlu'])->assertSessionHas('success');
        $this->assertSame('cancelled', $p2->fresh()->computedStatus());
        $this->as('management')->get('/action-plans')->assertOk();
        $this->as('risk_manager')->delete("/action-plans/{$p2->id}")->assertSessionHas('success');
        $this->as('risk_owner')->get("/action-plans/{$p->id}")->assertOk();
    }

    public function test_kri_values_trigger_alerts_and_notifications(): void
    {
        $risk = $this->makeRisk();
        $this->as('risk_officer')->post('/kris', ['name' => 'Downtime', 'unit' => 'jam', 'risk_id' => $risk->id, 'owner_id' => $this->users['risk_owner']->id, 'source' => 'manual', 'frequency' => 'monthly', 'direction' => 'up_bad', 'threshold_warn' => 1, 'threshold_crit' => 3])->assertSessionHas('success');
        $k = Kri::withoutGlobalScopes()->first();
        $this->assertSame('KRI-001', $k->code);
        $this->as('risk_officer')->post('/kris', ['name' => 'x', 'source' => 'manual', 'frequency' => 'monthly', 'direction' => 'up_bad', 'threshold_warn' => 5, 'threshold_crit' => 3])->assertSessionHasErrors('threshold_crit');
        $this->as('risk_officer')->post("/kris/{$k->id}/values", ['period' => '2026-09', 'value' => 0.5])->assertSessionHas('success');
        $this->assertSame('normal', $k->fresh()->status);
        $this->as('risk_officer')->post("/kris/{$k->id}/values", ['period' => '2026-10', 'value' => 3.5])->assertSessionHas('success');
        $this->assertSame('critical', $k->fresh()->status);
        $this->assertDatabaseHas('alerts', ['type' => 'kri_breach', 'severity' => 'critical']);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $this->users['risk_owner']->id]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $this->users['risk_manager']->id]);
        $this->as('risk_officer')->post("/kris/{$k->id}/values", ['period' => '2026-10', 'value' => 0.2]); // perbarui nilai periode sama
        $this->assertSame(2, $k->values()->count());
        $this->assertSame('normal', $k->fresh()->status);
        $this->as('risk_officer')->put("/kris/{$k->id}", ['name' => 'Downtime sistem', 'source' => 'manual', 'frequency' => 'monthly', 'direction' => 'up_bad', 'threshold_warn' => 0.1, 'threshold_crit' => 0.15])->assertSessionHas('success');
        $this->assertSame('critical', $k->fresh()->status);
        $this->as('risk_owner')->post('/kris', ['name' => 'x', 'source' => 'manual', 'frequency' => 'monthly', 'direction' => 'up_bad', 'threshold_warn' => 1, 'threshold_crit' => 3])->assertForbidden();
        $this->as('risk_manager')->delete("/kris/{$k->id}")->assertSessionHas('success');
    }

    public function test_incidents_losses_and_lessons(): void
    {
        $risk = $this->makeRisk();
        $this->as('risk_officer')->post('/incidents', ['title' => 'Server mati', 'occurred_at' => now()->subDay()->format('Y-m-d H:i'), 'risk_id' => $risk->id, 'loss_amount' => 5000000, 'status' => 'reported'])->assertRedirect();
        $i = Incident::withoutGlobalScopes()->first();
        $this->assertSame('INC-' . now()->year . '-001', $i->code);
        $this->assertDatabaseHas('loss_events', ['incident_id' => $i->id, 'amount' => 5000000]);
        $this->assertDatabaseHas('alerts', ['type' => 'incident']);
        $this->as('risk_officer')->post('/incidents', ['title' => 'x', 'occurred_at' => now()->addDays(2)->format('Y-m-d H:i'), 'status' => 'reported'])->assertSessionHasErrors('occurred_at');
        $this->as('risk_officer')->put("/incidents/{$i->id}", ['title' => 'Server mati 2 jam', 'occurred_at' => $i->occurred_at->format('Y-m-d H:i'), 'status' => 'closed'])->assertSessionHas('success');
        $this->assertNotNull($i->fresh()->closed_at);
        $this->as('risk_officer')->post("/incidents/{$i->id}/lessons", ['text' => 'belajar'])->assertSessionHas('success');
        $this->as('auditor')->get("/incidents/{$i->id}")->assertOk();
        $this->as('risk_manager')->post('/incidents/losses', ['year' => 2025, 'risk_name' => 'Lama', 'event' => 'Kejadian', 'amount' => 100])->assertSessionHas('success');
        $l = \App\Models\LossEvent::withoutGlobalScopes()->where('year', 2025)->first();
        $this->as('risk_manager')->put("/incidents/losses/{$l->id}", ['year' => 2025, 'risk_name' => 'Lama', 'event' => 'Kejadian 2', 'amount' => 200])->assertSessionHas('success');
        $this->as('risk_manager')->delete("/incidents/losses/{$l->id}")->assertSessionHas('success');
        $this->as('auditor')->get('/incidents/losses')->assertOk();
        $this->as('risk_manager')->delete("/incidents/{$i->id}")->assertRedirect('/incidents');
    }

    public function test_reviews_snapshot_and_documents(): void
    {
        Storage::fake('local');
        $risk = $this->makeRisk(['status' => 'monitoring']);
        $this->as('risk_manager')->post('/reviews/snapshot')->assertSessionHas('success');
        $this->assertDatabaseHas('risk_snapshots', ['risk_id' => $risk->id, 'period' => now()->format('Y-m')]);
        $this->as('risk_owner')->post('/reviews', ['risk_id' => $risk->id, 'period_type' => 'quarterly', 'period' => '2026-Q4', 'decision' => 'continue', 'note' => 'stabil'])->assertSessionHas('success');
        $this->assertDatabaseHas('reviews', ['risk_id' => $risk->id, 'current_score' => $risk->residual_score, 'reviewer_id' => $this->users['risk_owner']->id]);
        $this->as('risk_owner')->post('/reviews', ['risk_id' => $risk->id, 'period_type' => 'adhoc', 'period' => 'adhoc', 'decision' => 'close'])->assertSessionHas('success');
        $this->assertDatabaseHas('approvals', ['subject_id' => $risk->id, 'type' => 'closure']);
        $this->as('risk_owner')->get('/reviews')->assertOk();

        $file = UploadedFile::fake()->createWithContent('bukti.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF");
        $this->as('risk_owner')->post('/documents', ['file' => $file, 'title' => 'Bukti', 'type' => 'evidence', 'subject_kind' => 'risk', 'subject_id' => $risk->id])->assertSessionHas('success');
        $d = Document::withoutGlobalScopes()->first();
        $this->assertStringNotContainsString('bukti.pdf', $d->path); // nama acak
        $this->assertSame('risk', $d->subject_type);
        Storage::disk('local')->assertExists($d->path);
        $bad = UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload');
        $this->as('risk_owner')->post('/documents', ['file' => $bad, 'title' => 'x', 'type' => 'evidence'])->assertSessionHasErrors('file');
        $fakePdf = UploadedFile::fake()->createWithContent('skrip.pdf', '<?php echo 1;');
        $this->as('risk_owner')->post('/documents', ['file' => $fakePdf, 'title' => 'x', 'type' => 'evidence'])->assertSessionHasErrors('file');
        $this->as('auditor')->get("/documents/{$d->id}/download")->assertOk()->assertHeader('Content-Disposition');
        $this->assertDatabaseHas('audit_logs', ['subject_type' => 'document', 'action' => 'downloaded']);
        $riskB = $this->makeRisk([], $this->unitB);
        $fileB = UploadedFile::fake()->createWithContent('b.pdf', "%PDF-1.4\n%%EOF");
        $this->as('risk_manager')->post('/documents', ['file' => $fileB, 'title' => 'B', 'type' => 'evidence', 'subject_kind' => 'risk', 'subject_id' => $riskB->id]);
        $dB = Document::withoutGlobalScopes()->orderByDesc('id')->first();
        $this->as('risk_officer')->get("/documents/{$dB->id}/download")->assertForbidden(); // di luar cakupan unit
        $this->as('risk_owner')->put("/documents/{$d->id}", ['title' => 'Bukti 2', 'type' => 'report', 'status' => 'approved'])->assertSessionHas('success');
        $this->as('risk_owner')->delete("/documents/{$d->id}")->assertSessionHas('success');
        $this->assertSoftDeleted('documents', ['id' => $d->id]);
    }

    public function test_improvements_lessons_framework_alerts(): void
    {
        $this->as('risk_manager')->post('/improvements', ['source_type' => 'audit', 'title' => 'Perbaiki SOP', 'status' => 'open'])->assertSessionHas('success');
        $i = \App\Models\Improvement::withoutGlobalScopes()->first();
        $this->assertSame('IMP-' . now()->year . '-001', $i->code);
        $this->as('risk_manager')->put("/improvements/{$i->id}", ['source_type' => 'audit', 'title' => 'Perbaiki SOP', 'status' => 'done'])->assertSessionHas('success');
        $this->as('risk_manager')->post('/lessons', ['text' => 'pelajaran organisasi'])->assertSessionHas('success');
        $l = \App\Models\Lesson::withoutGlobalScopes()->first();
        $this->as('risk_manager')->delete("/lessons/{$l->id}")->assertSessionHas('success');
        $this->as('risk_manager')->delete("/improvements/{$i->id}")->assertSessionHas('success');
        $item = \App\Models\FrameworkItem::withoutGlobalScopes()->first();
        $this->as('risk_manager')->put("/framework/{$item->id}", ['status' => 'met', 'score' => 90])->assertSessionHas('success');
        $this->as('auditor')->put("/framework/{$item->id}", ['status' => 'met'])->assertForbidden();
        $this->as('auditor')->get('/framework')->assertOk();
        $this->as('auditor')->get('/improvements')->assertOk();

        $alert = app(\App\Services\AlertService::class)->raise('test', 'info', $this->makeRisk(), 'Uji', null, null, 'uji:1');
        $this->assertNull(app(\App\Services\AlertService::class)->raise('test', 'info', null, 'Uji', null, null, 'uji:1')); // dedupe
        $this->as('risk_owner')->post("/alerts/{$alert->id}/read")->assertRedirect();
        $this->as('risk_owner')->post("/alerts/{$alert->id}/handle")->assertSessionHas('success');
        $this->as('auditor')->post("/alerts/{$alert->id}/handle")->assertForbidden();
        $this->as('auditor')->get('/alerts')->assertOk();
        $this->as('auditor')->get('/alerts/latest')->assertOk()->assertJsonIsArray();
    }

    public function test_daily_sweep_and_snapshot_commands(): void
    {
        $risk = $this->makeRisk();
        $this->actingAs($this->users['risk_manager']);
        ActionPlan::create(['code' => 'AP-X', 'risk_id' => $risk->id, 'title' => 'Telat', 'priority' => 'high', 'due_date' => now()->subDays(20)->toDateString(), 'pic_id' => $this->users['risk_owner']->id, 'progress' => 10]);
        ActionPlan::create(['code' => 'AP-Y', 'risk_id' => $risk->id, 'title' => 'Besok', 'priority' => 'low', 'due_date' => now()->addDay()->toDateString(), 'pic_id' => $this->users['risk_owner']->id, 'progress' => 10]);
        auth()->logout();
        $this->artisan('manrisk:daily-sweep')->assertSuccessful();
        $this->assertDatabaseHas('alerts', ['type' => 'action_overdue', 'severity' => 'critical']);
        $this->assertDatabaseHas('alerts', ['type' => 'action_due']);
        $this->artisan('manrisk:daily-sweep')->assertSuccessful();
        $this->assertSame(1, \App\Models\Alert::withoutGlobalScopes()->where('type', 'action_overdue')->count()); // tidak duplikat
        $this->artisan('manrisk:snapshot', ['--period' => '2026-01'])->assertSuccessful();
        $this->assertDatabaseHas('risk_snapshots', ['risk_id' => $risk->id, 'period' => '2026-01']);
    }

    public function test_reports_and_ai(): void
    {
        $this->makeRisk();
        $this->as('management')->post('/reports', ['type' => 'register', 'format' => 'xlsx'])->assertOk()->assertHeader('Content-Disposition');
        $this->as('management')->post('/reports', ['type' => 'executive', 'format' => 'pdf'])->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->as('management')->post('/reports', ['type' => 'controls', 'format' => 'pdf'])->assertOk();
        $this->as('management')->post('/reports', ['type' => 'salah', 'format' => 'pdf'])->assertSessionHasErrors('type');
        $this->assertDatabaseHas('report_jobs', ['type' => 'register', 'status' => 'done']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'exported']);
        $risk = \App\Models\Risk::withoutGlobalScopes()->first();
        $this->as('risk_owner')->postJson('/ai', ['feature' => 'explain_score', 'risk_id' => $risk->id])->assertOk()->assertJsonStructure(['text', 'model', 'remaining']);
        $this->as('risk_owner')->postJson('/ai', ['feature' => 'summarize'])->assertOk();
        $this->as('risk_owner')->postJson('/ai', ['feature' => 'hack'])->assertStatus(422);
        $this->assertDatabaseHas('ai_interactions', ['feature' => 'explain_score', 'user_id' => $this->users['risk_owner']->id]);
        $riskB = $this->makeRisk([], $this->unitB);
        $this->as('risk_officer')->postJson('/ai', ['feature' => 'explain_score', 'risk_id' => $riskB->id])->assertForbidden();
    }

    public function test_action_plan_completion_requires_owner_verification(): void
    {
        Storage::fake('local');
        $risk = $this->makeRisk(['status' => 'treating', 'residual_l' => 2, 'residual_i' => 2]);
        $this->as('risk_owner')->post('/action-plans', ['risk_id' => $risk->id, 'title' => 'Mitigasi', 'priority' => 'high', 'due_date' => now()->addMonth()->toDateString(), 'pic_id' => $this->users['risk_officer']->id]);
        $p = ActionPlan::withoutGlobalScopes()->first();
        $this->as('risk_officer')->post("/action-plans/{$p->id}/progress", ['progress' => 100, 'note' => 'beres', 'evidence' => UploadedFile::fake()->createWithContent('b.pdf', "%PDF-1.4\n%%EOF")])->assertSessionHas('success');
        $p->refresh();
        $this->assertSame('verify', $p->computedStatus());
        $this->assertSame('treating', $risk->fresh()->status);
        $this->assertDatabaseHas('alerts', ['type' => 'plan_verify']);
        $this->as('risk_officer')->post("/action-plans/{$p->id}/verify", ['action' => 'approve'])->assertForbidden();
        $this->as('risk_owner')->post("/action-plans/{$p->id}/verify", ['action' => 'reject'])->assertSessionHasErrors('note');
        $this->as('risk_owner')->post("/action-plans/{$p->id}/verify", ['action' => 'reject', 'note' => 'bukti kurang'])->assertSessionHas('success');
        $this->assertSame(90, (int) $p->fresh()->progress);
        $this->as('risk_officer')->post("/action-plans/{$p->id}/progress", ['progress' => 100, 'note' => 'lengkap'])->assertSessionHas('success');
        $this->as('risk_owner')->post("/action-plans/{$p->id}/verify", ['action' => 'approve'])->assertSessionHas('success');
        $this->assertSame('done', $p->fresh()->computedStatus());
        $this->assertSame('monitoring', $risk->fresh()->status);
    }
}

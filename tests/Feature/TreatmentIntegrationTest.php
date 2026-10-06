<?php

namespace Tests\Feature;

use App\Models\ActionPlan;
use App\Models\Alert;
use App\Models\Control;
use App\Models\Document;
use App\Models\Improvement;
use App\Models\Incident;
use App\Models\Kri;
use App\Models\LossEvent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Integrasi lintas modul penanganan: kontrol, KRI, insiden, action plan, improvement, dokumen. */
class TreatmentIntegrationTest extends TestCase
{
    private function props($res): array
    {
        return $res->viewData('page')['props'];
    }

    public function test_control_edit_by_unit_scoped_user_keeps_other_unit_risk_links_and_failure_alerts(): void
    {
        $riskA = $this->makeRisk();
        $riskB = $this->makeRisk([], $this->unitB);
        $this->as('risk_manager')->post('/controls', ['name' => 'Rekonsiliasi', 'type' => 'detective', 'mode' => 'manual', 'frequency' => 'monthly', 'unit_id' => $this->unitA->id, 'risk_ids' => [$riskA->id, $riskB->id]])->assertSessionHas('success');
        $c = Control::withoutGlobalScopes()->first();
        $payload = ['name' => 'Rekonsiliasi 2', 'type' => 'detective', 'mode' => 'manual', 'frequency' => 'monthly', 'unit_id' => $this->unitA->id];
        $this->as('risk_officer')->get('/controls')->assertInertia(fn ($p) => $p->where('controls.0.risks', fn ($r) => collect($r)->pluck('id')->all() === [$riskA->id]));
        $this->as('risk_officer')->put("/controls/{$c->id}", $payload + ['risk_ids' => [$riskA->id]])->assertSessionHas('success');
        $this->assertEqualsCanonicalizing([$riskA->id, $riskB->id], $c->risks()->pluck('risks.id')->all());
        $this->as('risk_officer')->put("/controls/{$c->id}", $payload + ['risk_ids' => []])->assertSessionHas('success');
        $this->assertSame([$riskB->id], $c->risks()->pluck('risks.id')->all()); // hanya tautan unit sendiri yang dilepas
        $this->as('risk_manager')->get("/controls?risk_id={$riskB->id}")->assertInertia(fn ($p) => $p->has('controls', 1)->where('filter_risk.id', $riskB->id));
        $this->as('risk_manager')->get("/controls?risk_id={$riskA->id}")->assertInertia(fn ($p) => $p->has('controls', 0));

        // kontrol tanpa risiko tetap memicu peringatan & improvement yang tertaut ke kontrol
        $this->as('risk_manager')->post('/controls', ['name' => 'Tanpa risiko', 'type' => 'preventive', 'mode' => 'manual', 'frequency' => 'monthly', 'owner_id' => $this->users['risk_owner']->id]);
        $solo = Control::withoutGlobalScopes()->where('name', 'Tanpa risiko')->first();
        $this->as('risk_manager')->post("/controls/{$solo->id}/assess", ['tested_at' => now()->toDateString(), 'design_eff' => 1, 'operating_eff' => 1, 'note' => 'gagal'])->assertSessionHas('success');
        $this->assertDatabaseHas('alerts', ['type' => 'control_failure', 'subject_type' => 'control', 'subject_id' => $solo->id, 'link' => "/controls/{$solo->id}", 'handled_at' => null]);
        $this->assertDatabaseHas('improvements', ['source_type' => 'control_failure', 'subject_type' => 'control', 'subject_id' => $solo->id]);
        $this->as('risk_manager')->get("/controls/{$solo->id}")->assertInertia(fn ($p) => $p->has('control.improvements', 1)->has('plans'));
        $this->as('risk_manager')->post("/controls/{$solo->id}/assess", ['tested_at' => now()->toDateString(), 'design_eff' => 3, 'operating_eff' => 4]);
        $this->assertSame(0, Alert::withoutGlobalScopes()->where('subject_type', 'control')->where('subject_id', $solo->id)->whereNull('handled_at')->count());
    }

    public function test_kri_threshold_edit_alerts_opens_improvement_and_normal_resolves(): void
    {
        $risk = $this->makeRisk();
        $this->actingAs($this->users['risk_manager']);
        $kri = Kri::create(['code' => 'KRI-001', 'name' => 'Downtime', 'unit' => 'jam', 'risk_id' => $risk->id, 'owner_id' => $this->users['risk_owner']->id, 'source' => 'manual', 'frequency' => 'monthly', 'direction' => 'up_bad', 'threshold_warn' => 10, 'threshold_crit' => 20, 'last_value' => 5, 'status' => 'normal']);
        $base = ['name' => 'Downtime', 'unit' => 'jam', 'risk_id' => $risk->id, 'owner_id' => $this->users['risk_owner']->id, 'source' => 'manual', 'frequency' => 'monthly', 'direction' => 'up_bad'];
        $this->put("/kris/{$kri->id}", $base + ['threshold_warn' => 2, 'threshold_crit' => 4])->assertSessionHas('success');
        $this->assertSame('critical', $kri->fresh()->status);
        $this->assertDatabaseHas('improvements', ['source_type' => 'kri_breach', 'subject_type' => 'kri', 'subject_id' => $kri->id, 'risk_id' => $risk->id]);
        $this->assertDatabaseHas('alerts', ['type' => 'kri_breach', 'subject_type' => 'kri', 'subject_id' => $kri->id, 'handled_at' => null]);
        $imp = Improvement::withoutGlobalScopes()->where('subject_type', 'kri')->first();
        $this->get("/kris?risk_id={$risk->id}")->assertInertia(fn ($p) => $p->has('kris', 1)->where('kris.0.improvement.id', $imp->id)->where('filter_labels.risk.id', $risk->id));
        $this->get('/kris?status=breach')->assertInertia(fn ($p) => $p->has('kris', 1));
        $this->get("/kris?unit_id={$this->unitB->id}")->assertInertia(fn ($p) => $p->has('kris', 0));
        $this->put("/kris/{$kri->id}", $base + ['threshold_warn' => 10, 'threshold_crit' => 20])->assertSessionHas('success');
        $this->assertSame('normal', $kri->fresh()->status);
        $this->assertSame(0, Alert::withoutGlobalScopes()->where('subject_type', 'kri')->where('subject_id', $kri->id)->whereNull('handled_at')->count());
    }

    public function test_incident_unit_default_loss_sync_and_close_resolves_alert(): void
    {
        $risk = $this->makeRisk([], $this->unitA);
        $this->as('risk_manager')->post('/incidents', ['title' => 'Server mati', 'occurred_at' => now()->subHour()->format('Y-m-d H:i'), 'risk_id' => $risk->id, 'loss_amount' => 1000, 'status' => 'reported'])->assertRedirect();
        $inc = Incident::withoutGlobalScopes()->first();
        $this->assertSame($this->unitA->id, $inc->unit_id); // unit default dari risiko
        $this->assertSame(1, LossEvent::withoutGlobalScopes()->where('incident_id', $inc->id)->count());
        $this->assertDatabaseHas('alerts', ['type' => 'incident', 'subject_type' => 'incident', 'subject_id' => $inc->id, 'handled_at' => null]);
        $this->as('risk_officer')->get('/incidents?status=open&unit_id=' . $this->unitA->id)->assertInertia(fn ($p) => $p->has('incidents.data', 1));
        $this->as('risk_officer')->get("/incidents/{$inc->id}")->assertInertia(fn ($p) => $p->has('risks')->has('units')->has('incident.improvements'));
        $this->as('risk_officer')->put("/incidents/{$inc->id}", ['title' => 'Server mati', 'occurred_at' => now()->subHour()->format('Y-m-d H:i'), 'risk_id' => $risk->id, 'unit_id' => $this->unitA->id, 'loss_amount' => 2500, 'status' => 'closed'])->assertSessionHas('success');
        $loss = LossEvent::withoutGlobalScopes()->where('incident_id', $inc->id)->sole();
        $this->assertEquals(2500, (float) $loss->amount);
        $this->assertSame(0, Alert::withoutGlobalScopes()->where('subject_type', 'incident')->where('subject_id', $inc->id)->whereNull('handled_at')->count());
        $this->as('risk_officer')->get('/incidents?status=open')->assertInertia(fn ($p) => $p->has('incidents.data', 0));
        // unit-scoped tidak boleh menautkan risiko unit lain; loss database dibatasi & hanya pengelola pusat yang menulis
        $riskB = $this->makeRisk([], $this->unitB);
        $this->as('risk_officer')->put("/incidents/{$inc->id}", ['title' => 'x', 'occurred_at' => now()->subHour()->format('Y-m-d H:i'), 'risk_id' => $riskB->id, 'status' => 'closed'])->assertSessionHasErrors('risk_id');
        $this->as('risk_officer')->post('/incidents/losses', ['year' => 2025, 'risk_name' => 'x', 'event' => 'y', 'amount' => 1])->assertForbidden();
        $this->actingAs($this->users['risk_manager']);
        LossEvent::create(['year' => 2025, 'risk_name' => 'Lain', 'event' => 'e', 'amount' => 5]);
        $this->as('risk_officer')->get('/incidents/losses')->assertInertia(fn ($p) => $p->has('losses', 1)->where('losses.0.incident.risk.id', $risk->id));
    }

    public function test_plan_pic_from_other_unit_and_cancel_reevaluates_risk(): void
    {
        $riskB = $this->makeRisk(['status' => 'treating', 'residual_l' => 1, 'residual_i' => 2], $this->unitB);
        $this->actingAs($this->users['risk_manager']);
        $done = ActionPlan::create(['code' => 'AP-1', 'risk_id' => $riskB->id, 'title' => 'Selesai', 'priority' => 'low', 'due_date' => now()->addMonth(), 'unit_id' => $this->unitB->id, 'progress' => 100, 'verified_at' => now()]);
        $open = ActionPlan::create(['code' => 'AP-2', 'risk_id' => $riskB->id, 'title' => 'Berjalan', 'priority' => 'low', 'due_date' => now()->subDays(3), 'unit_id' => $this->unitB->id, 'progress' => 20, 'pic_id' => $this->users['risk_officer']->id]);
        app(\App\Services\AlertService::class)->raise('action_overdue', 'warning', $open, 'terlambat', null, '/x', 'action:test');
        $this->as('risk_officer')->get("/action-plans/{$open->id}")->assertOk()->assertInertia(fn ($p) => $p->where('can.progress', true)->where('can.view_risk', false));
        $this->as('risk_officer')->get("/action-plans/{$done->id}")->assertForbidden();
        $this->as('risk_manager')->get("/action-plans?unit_id={$this->unitB->id}&status=overdue")->assertInertia(fn ($p) => $p->has('plans', 1));
        $this->as('risk_manager')->post("/action-plans/{$open->id}/cancel", ['reason' => 'tidak relevan'])->assertSessionHas('success');
        $this->assertSame('monitoring', $riskB->fresh()->status);
        $this->assertNotNull(Alert::withoutGlobalScopes()->where('dedupe_key', 'action:test')->value('handled_at'));
        // pindah risiko: harus berwenang atas risiko tujuan; unit mengikuti risiko bila kosong
        $riskA = $this->makeRisk();
        $this->as('risk_manager')->put("/action-plans/{$done->id}", ['risk_id' => $riskA->id, 'title' => 'Selesai', 'priority' => 'low', 'due_date' => now()->addMonth()->toDateString()])->assertSessionHas('success');
        $this->assertSame($this->unitA->id, $done->fresh()->unit_id);
    }

    public function test_improvements_filters_scoping_and_risk_link(): void
    {
        $riskA = $this->makeRisk();
        $riskB = $this->makeRisk([], $this->unitB);
        $this->actingAs($this->users['risk_manager']);
        $kri = Kri::create(['code' => 'KRI-001', 'name' => 'K', 'risk_id' => $riskA->id, 'source' => 'manual', 'frequency' => 'monthly', 'direction' => 'up_bad', 'threshold_warn' => 1, 'threshold_crit' => 2, 'status' => 'normal']);
        $mk = fn ($code, $attrs) => Improvement::create($attrs + ['code' => $code, 'source_type' => 'audit', 'title' => $code, 'status' => 'open']);
        $mk('IMP-A', ['unit_id' => $this->unitA->id]);
        $mk('IMP-B', ['unit_id' => $this->unitB->id]);
        $mk('IMP-BPIC', ['unit_id' => $this->unitB->id, 'pic_id' => $this->users['risk_officer']->id]);
        $mk('IMP-KRI', ['source_type' => 'kri_breach', 'subject_type' => 'kri', 'subject_id' => $kri->id, 'risk_id' => $riskA->id, 'status' => 'in_progress']);
        $codes = fn ($res) => collect($this->props($res)['items'])->pluck('code')->sort()->values()->all();
        $this->assertSame(['IMP-A', 'IMP-BPIC', 'IMP-KRI'], $codes($this->as('risk_officer')->get('/improvements')));
        $this->assertSame(['IMP-KRI'], $codes($this->as('risk_officer')->get("/improvements?subject_type=kri&subject_id={$kri->id}")));
        $this->assertSame(['IMP-KRI'], $codes($this->as('risk_manager')->get("/improvements?risk_id={$riskA->id}")));
        $this->assertSame(['IMP-A', 'IMP-B', 'IMP-BPIC'], $codes($this->as('risk_manager')->get('/improvements?status=open&source_type=audit')));
        $r = $this->as('risk_officer')->get("/improvements?subject_type=kri&subject_id={$kri->id}");
        $this->assertSame(['type' => 'kri', 'id' => $kri->id, 'code' => 'KRI-001', 'name' => 'K', 'risk_id' => $riskA->id], $this->props($r)['items'][0]['subject']);
        $this->assertSame('KRI-001', explode(' ', $this->props($r)['filter_labels']['subject'])[1]);
        $pic = collect($this->props($this->as('risk_officer')->get('/improvements'))['items'])->firstWhere('code', 'IMP-BPIC');
        $this->assertTrue($pic['can_update']);
        // risiko/sumber terkait divalidasi cakupan unit
        $this->as('risk_officer')->post('/improvements', ['source_type' => 'audit', 'title' => 'X', 'status' => 'open', 'risk_id' => $riskB->id])->assertForbidden();
        $this->as('risk_officer')->post('/improvements', ['source_type' => 'kri_breach', 'title' => 'Y', 'status' => 'open', 'subject_type' => 'kri', 'subject_id' => $kri->id])->assertSessionHas('success');
        $this->assertDatabaseHas('improvements', ['title' => 'Y', 'subject_type' => 'kri', 'subject_id' => $kri->id, 'risk_id' => $riskA->id, 'unit_id' => $this->unitA->id, 'source_ref' => 'KRI-001']);
        $this->as('risk_manager')->post('/improvements', ['source_type' => 'audit', 'title' => 'Manual', 'status' => 'open'])->assertSessionHas('success'); // sumber manual tetap jalan
    }

    public function test_documents_index_exposes_subject_and_prefill_filter(): void
    {
        Storage::fake('local');
        $risk = $this->makeRisk();
        $this->as('risk_manager')->post('/controls', ['name' => 'Kontrol', 'type' => 'detective', 'mode' => 'manual', 'frequency' => 'monthly', 'unit_id' => $this->unitA->id, 'risk_ids' => [$risk->id]]);
        $c = Control::withoutGlobalScopes()->first();
        $pdf = fn () => UploadedFile::fake()->createWithContent('bukti.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
        $this->as('risk_officer')->post('/documents', ['file' => $pdf(), 'title' => 'Bukti uji', 'type' => 'evidence', 'subject_kind' => 'control', 'subject_id' => $c->id])->assertSessionHas('success');
        $this->as('risk_officer')->post('/documents', ['file' => $pdf(), 'title' => 'Bukti risiko', 'type' => 'evidence', 'subject_kind' => 'risk', 'subject_id' => $risk->id])->assertSessionHas('success');
        $this->as('risk_officer')->get('/documents')->assertInertia(fn ($p) => $p->has('documents.data', 2)->where('subject_options.control.0.id', $c->id)->has('subject_options.action_plan')->has('subject_options.improvement'));
        $docs = collect($this->props($this->as('risk_officer')->get('/documents'))['documents']['data']);
        $this->assertSame(['control', $c->id, 'Kontrol'], [$docs->firstWhere('title', 'Bukti uji')['subject_type'], $docs->firstWhere('title', 'Bukti uji')['subject_id'], $docs->firstWhere('title', 'Bukti uji')['subject']['label']]);
        $this->as('risk_officer')->get("/documents?subject_kind=control&subject_id={$c->id}&upload=1")
            ->assertInertia(fn ($p) => $p->has('documents.data', 1)->where('documents.data.0.title', 'Bukti uji')->where('subject.type', 'control')->where('subject.id', $c->id)->where('filters.upload', '1'));
        // subjek di luar cakupan tidak bocor
        $ctlB = Control::withoutGlobalScopes()->create(['organization_id' => $this->org->id, 'code' => 'C-B', 'name' => 'B', 'frequency' => 'monthly', 'type' => 'preventive', 'mode' => 'manual', 'unit_id' => $this->unitB->id]);
        $this->as('risk_officer')->get("/documents?subject_kind=control&subject_id={$ctlB->id}")->assertInertia(fn ($p) => $p->has('documents.data', 0)->where('subject', null));
        $this->assertSame(2, Document::withoutGlobalScopes()->count());
    }
}

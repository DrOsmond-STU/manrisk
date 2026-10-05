<?php

namespace Tests\Feature;

use App\Models\ActionPlan;
use App\Models\Control;
use App\Models\Document;
use App\Models\Improvement;
use App\Models\Incident;
use App\Models\Kri;
use App\Models\Risk;
use App\Services\ImportService;
use App\Services\ReportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class NewFeaturesTest extends TestCase
{
    private function xlsx(array $rows): UploadedFile
    {
        $ss = new Spreadsheet();
        $ss->getActiveSheet()->fromArray($rows, null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'x') . '.xlsx';
        (new Xlsx($ss))->save($path);
        return new UploadedFile($path, 'impor.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_register_filters_heatmap_cell_objective_no_controls_and_export(): void
    {
        $a = $this->makeRisk(['residual_l' => 3, 'residual_i' => 3]);
        $b = $this->makeRisk(['residual_l' => 2, 'residual_i' => 2, 'name' => 'Lain']);
        $c = Control::create(['code' => 'C-1', 'name' => 'K', 'frequency' => 'monthly', 'type' => 'preventive', 'mode' => 'manual', 'organization_id' => $this->org->id]);
        $c->risks()->sync([$b->id]);
        $this->as('risk_manager')->get('/risks?mode=residual&l=3&i=3')->assertInertia(fn ($p) => $p->where('risks.total', 1)->where('risks.data.0.id', $a->id));
        $this->as('risk_manager')->get('/risks?no_controls=1')->assertInertia(fn ($p) => $p->where('risks.total', 1)->where('risks.data.0.id', $a->id));
        $this->as('risk_manager')->get('/risks?q=' . urlencode('%'))->assertOk();
        $this->as('risk_manager')->get('/risks/export?format=xlsx&l=3&i=3')->assertOk()->assertHeader('Content-Disposition');
        $this->as('risk_manager')->get('/risks/export?format=pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->as('risk_manager')->get('/risks/export?format=exe')->assertSessionHasErrors('format');
    }

    public function test_duplicate_detection(): void
    {
        $this->makeRisk(['name' => 'Gangguan layanan pusat data utama', 'event' => 'pusat data berhenti beroperasi']);
        $this->as('risk_officer')->getJson('/risks/similar?unit_id=' . $this->unitA->id . '&text=' . urlencode('Gangguan layanan pusat data utama'))->assertOk()->assertJsonCount(1);
        $this->as('risk_officer')->getJson('/risks/similar?unit_id=' . $this->unitA->id . '&text=' . urlencode('Kesalahan perhitungan gaji pegawai kontrak'))->assertOk()->assertJsonCount(0);
        $this->as('risk_officer')->getJson('/risks/similar?unit_id=' . $this->unitB->id . '&text=' . urlencode('Gangguan layanan pusat data utama'))->assertOk()->assertJsonCount(0);
    }

    public function test_risk_import_preview_and_commit_only_valid_rows(): void
    {
        $head = array_values(ImportService::RISK_COLUMNS);
        $ok = ['Risiko impor satu', 'A', 'Strategis', 'risk_owner@uji.test', '', 'sebab', 'kejadian', 'akibat', 'internal', 'process', '', 4, 4, 3, 3, 2, 2, 'reduce'];
        $badUnit = ['Salah unit', 'ZZ', 'Strategis', 'risk_owner@uji.test', '', 'a', 'b', 'c', 'internal', 'process', '', 4, 4, 3, 3, 2, 2, 'reduce'];
        $badScore = ['Skor salah', 'A', 'Strategis', 'risk_owner@uji.test', '', 'a', 'b', 'c', 'internal', 'process', '', 2, 2, 4, 4, 1, 1, 'reduce'];
        $outScope = ['Unit B', 'B', 'Strategis', 'risk_owner@uji.test', '', 'a', 'b', 'c', 'internal', 'process', '', 4, 4, 3, 3, 2, 2, 'reduce'];
        $this->as('risk_officer')->get('/import/risk')->assertOk();
        $this->as('risk_officer')->get('/import/risk/template')->assertOk()->assertDownload('templat-impor-risk.xlsx');
        $r = $this->as('risk_officer')->post('/import/risk/preview', ['file' => $this->xlsx([$head, $ok, $badUnit, $badScore, $outScope])]);
        $r->assertOk()->assertInertia(fn ($p) => $p->where('preview.total', 4)->has('preview.valid', 1)->has('preview.errors', 3));
        $token = $r->viewData('page')['props']['preview']['token'];
        $this->as('risk_officer')->post('/import/risk/commit', ['token' => $token])->assertRedirect();
        $this->assertSame(1, Risk::withoutGlobalScopes()->count());
        $this->assertSame('draft', Risk::withoutGlobalScopes()->first()->status);
        $this->as('risk_officer')->post('/import/risk/commit', ['token' => $token])->assertSessionHas('error'); // token sekali pakai
        $this->as('auditor')->get('/import/risk')->assertForbidden();
        $this->as('risk_officer')->post('/import/risk/preview', ['file' => $this->xlsx([['Kolom salah']])])->assertSessionHasErrors('file');
        $this->as('risk_officer')->get('/import/hack')->assertNotFound();
    }

    public function test_kri_import_updates_values_and_status(): void
    {
        $risk = $this->makeRisk();
        $this->actingAs($this->users['risk_manager']);
        $kri = Kri::create(['code' => 'KRI-001', 'name' => 'Downtime', 'risk_id' => $risk->id, 'source' => 'manual', 'frequency' => 'monthly', 'direction' => 'up_bad', 'threshold_warn' => 1, 'threshold_crit' => 3, 'status' => 'normal']);
        $r = $this->post('/import/kri/preview', ['file' => $this->xlsx([array_values(ImportService::KRI_COLUMNS), ['KRI-001', '2026-08', 0.5, 'x'], ['KRI-001', '2026-09', 4, 'y'], ['KRI-999', '2026-09', 1, ''], ['KRI-001', '2026-13', 1, '']])]);
        $r->assertInertia(fn ($p) => $p->has('preview.valid', 2)->has('preview.errors', 2));
        $this->post('/import/kri/commit', ['token' => $r->viewData('page')['props']['preview']['token']])->assertRedirect('/kris');
        $this->assertSame('critical', $kri->fresh()->status);
        $this->assertSame(2, $kri->values()->count());
        $this->assertDatabaseHas('improvements', ['source_type' => 'kri_breach', 'source_ref' => 'KRI-001']);
        $this->assertSame('treating', $risk->fresh()->status); // Dipantau → Dalam Penanganan
    }

    public function test_wizard_initial_plan_incident_prefill_and_retain_justification(): void
    {
        $this->actingAs($this->users['risk_officer']);
        $inc = Incident::create(['code' => 'INC-1', 'title' => 'Server mati', 'occurred_at' => now()->subDay(), 'unit_id' => $this->unitA->id, 'status' => 'reported', 'cause' => 'listrik', 'impact' => 'layanan berhenti']);
        $this->get("/risks/create?incident={$inc->id}")->assertInertia(fn ($p) => $p->where('prefill.from_incident', $inc->id)->where('prefill.name', 'Server mati'));
        $this->post('/risks', $this->riskPayload(['plan_title' => 'Pasang UPS', 'plan_due' => now()->addMonth()->toDateString(), 'from_incident' => $inc->id]))->assertRedirect();
        $risk = Risk::withoutGlobalScopes()->latest('id')->first();
        $this->assertDatabaseHas('action_plans', ['risk_id' => $risk->id, 'title' => 'Pasang UPS']);
        $this->assertSame($risk->id, $inc->fresh()->risk_id);
        $retain = $this->makeRisk(['status' => 'draft', 'treatment' => 'retain', 'residual_l' => 4, 'residual_i' => 4]);
        $this->post("/risks/{$retain->id}/submit", [])->assertSessionHasErrors('note');
        $this->post("/risks/{$retain->id}/submit", ['note' => 'Biaya mitigasi melebihi manfaat'])->assertSessionHas('success');
        $this->assertDatabaseHas('approvals', ['subject_id' => $retain->id, 'type' => 'retain']);
        $this->assertSame('management', \App\Models\Approval::withoutGlobalScopes()->where('subject_id', $retain->id)->first()->steps->last()->role);
    }

    public function test_ineffective_control_opens_improvement_and_flags_risks(): void
    {
        $risk = $this->makeRisk();
        $this->as('risk_manager')->post('/controls', ['name' => 'Rekonsiliasi', 'type' => 'detective', 'mode' => 'manual', 'frequency' => 'monthly', 'risk_ids' => [$risk->id]]);
        $c = Control::withoutGlobalScopes()->first();
        $this->as('risk_manager')->post("/controls/{$c->id}/assess", ['tested_at' => now()->toDateString(), 'design_eff' => 3, 'operating_eff' => 1, 'note' => 'tidak jalan'])->assertSessionHas('success');
        $this->assertDatabaseHas('improvements', ['source_type' => 'control_failure', 'source_ref' => $c->code]);
        $this->assertDatabaseHas('alerts', ['type' => 'control_failure', 'subject_type' => 'risk', 'subject_id' => $risk->id]);
        $this->as('risk_manager')->post("/controls/{$c->id}/assess", ['tested_at' => now()->toDateString(), 'design_eff' => 1, 'operating_eff' => 1]);
        $this->assertSame(1, Improvement::withoutGlobalScopes()->where('source_type', 'control_failure')->count()); // tidak duplikat
    }

    public function test_review_minutes_document_versions_audit_csv(): void
    {
        Storage::fake('local');
        $risk = $this->makeRisk();
        $this->as('risk_owner')->post('/reviews', ['risk_id' => $risk->id, 'period_type' => 'quarterly', 'period' => '2026-Q4', 'decision' => 'continue']);
        $this->assertDatabaseHas('reviews', ['risk_id' => $risk->id, 'signed_ip' => '127.0.0.1']);
        $this->as('risk_manager')->get('/reviews/minutes?period=2026-Q4')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->as('risk_manager')->get('/reviews/minutes?period=1999-Q1')->assertNotFound();

        $pdf = fn ($n) => UploadedFile::fake()->createWithContent($n, "%PDF-1.4\n%%EOF");
        $this->as('risk_owner')->post('/documents', ['file' => $pdf('sop.pdf'), 'title' => 'SOP', 'type' => 'sop', 'subject_kind' => 'risk', 'subject_id' => $risk->id]);
        $v1 = Document::withoutGlobalScopes()->first();
        $this->as('risk_owner')->post('/documents', ['file' => $pdf('sop2.pdf'), 'title' => 'SOP', 'type' => 'sop', 'replaces_id' => $v1->id]);
        $v2 = Document::withoutGlobalScopes()->latest('id')->first();
        $this->assertSame(2, $v2->version);
        $this->assertSame('risk', $v2->subject_type);
        $this->as('risk_owner')->post('/documents', ['file' => $pdf('sop3.pdf'), 'title' => 'SOP', 'type' => 'sop', 'replaces_id' => $v2->id]);
        $this->assertSame(3, Document::withoutGlobalScopes()->max('version'));
        $this->as('risk_owner')->get("/documents?history={$v1->id}")->assertInertia(fn ($p) => $p->where('documents.total', 3));
        $this->as('risk_owner')->get("/documents/{$v1->id}/download")->assertOk();

        $csv = $this->as('auditor')->get('/admin/audit/export');
        $csv->assertOk();
        $this->assertStringContainsString('aksi', $csv->streamedContent());
        $this->as('risk_officer')->get('/admin/audit/export')->assertForbidden();
    }

    public function test_ai_structured_identify_and_statement(): void
    {
        $this->as('risk_officer')->postJson('/ai', ['feature' => 'identify', 'context' => 'pengadaan barang'])->assertOk()->assertJsonCount(5, 'data.candidates')->assertJsonStructure(['data' => ['candidates' => [['name', 'category', 'cause', 'event', 'impact', 'likelihood', 'impact_score']]]]);
        $this->as('risk_officer')->postJson('/ai', ['feature' => 'statement', 'context' => 'Karena server tunggal, dapat terjadi layanan berhenti, sehingga masyarakat tidak terlayani'])
            ->assertOk()->assertJsonPath('data.cause', 'server tunggal')->assertJsonPath('data.impact', 'masyarakat tidak terlayani');
        $this->as('auditor')->postJson('/ai', ['feature' => 'summarize'])->assertForbidden();
        $this->as('auditor')->get('/ai')->assertForbidden();
    }

    public function test_every_report_type_and_format_generates(): void
    {
        $risk = $this->makeRisk();
        $this->actingAs($this->users['risk_manager']);
        ActionPlan::create(['code' => 'AP-1', 'risk_id' => $risk->id, 'title' => 'Telat', 'priority' => 'high', 'due_date' => now()->subDays(3)->toDateString(), 'progress' => 20]);
        $this->artisan('manrisk:snapshot');
        foreach (ReportService::FORMATS as $type => $formats) {
            foreach ($formats as $fmt) {
                $r = $this->post('/reports', ['type' => $type, 'format' => $fmt]);
                $this->assertSame(200, $r->getStatusCode(), "laporan {$type}.{$fmt} gagal: " . json_encode(session('error')));
                $r->assertHeader('Content-Disposition');
            }
        }
        $this->post('/reports', ['type' => 'executive', 'format' => 'xlsx'])->assertSessionHasErrors('format');
        $this->assertSame(0, \App\Models\ReportJob::withoutGlobalScopes()->where('status', 'failed')->count());
    }

    public function test_dashboards_expose_spec_metrics(): void
    {
        $this->makeRisk();
        $this->as('risk_manager')->get('/dashboard')->assertInertia(fn ($p) => $p->has('metrics.realization')->has('metrics.effectiveness')->has('metrics.journey.projected')
            ->has('by_objective')->has('by_process')->has('heat_inherent')->has('recent')->has('emerging')->where('kpi.no_controls', 1)->has('ai_summary'));
        $this->as('risk_manager')->get('/dashboard?unit_id=' . $this->unitB->id)->assertInertia(fn ($p) => $p->where('kpi.total', 0));
        $this->as('management')->get('/dashboard/executive')->assertInertia(fn ($p) => $p->has('quarters', 4)->has('heat.inherent')->has('movement.inherent')->has('metrics.journey'));
    }

    public function test_my_action_plans_filter(): void
    {
        $risk = $this->makeRisk();
        $this->actingAs($this->users['risk_manager']);
        ActionPlan::create(['code' => 'AP-1', 'risk_id' => $risk->id, 'title' => 'Milik officer', 'priority' => 'low', 'due_date' => now()->addDay()->toDateString(), 'pic_id' => $this->users['risk_officer']->id]);
        ActionPlan::create(['code' => 'AP-2', 'risk_id' => $risk->id, 'title' => 'Milik owner', 'priority' => 'low', 'due_date' => now()->addDay()->toDateString(), 'pic_id' => $this->users['risk_owner']->id]);
        $this->as('risk_officer')->get('/action-plans?mine=1')->assertInertia(fn ($p) => $p->has('plans', 1)->where('plans.0.title', 'Milik officer'));
    }

    public function test_scheduled_report_is_emailed_with_attachment(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $this->makeRisk();
        $this->as('risk_manager')->post('/reports/schedules', ['type' => 'kri', 'format' => 'pdf', 'frequency' => 'monthly', 'day' => now()->day > 28 ? 28 : now()->day, 'recipients' => ['direksi@uji.test'], 'unit_id' => null])->assertSessionHas('success');
        $this->as('risk_manager')->post('/reports/schedules', ['type' => 'kri', 'format' => 'pdf', 'frequency' => 'monthly', 'day' => 1, 'recipients' => ['bukan-email']])->assertSessionHasErrors('recipients.0');
        auth()->logout();
        $this->artisan('manrisk:scheduled-reports --force')->assertSuccessful();
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\ScheduledReportMail::class, fn ($m) => $m->hasTo('direksi@uji.test') && count($m->attachments()) === 1);
        $s = \App\Models\ReportSchedule::withoutGlobalScopes()->first();
        $this->assertNotNull($s->last_sent_at);
        $this->as('risk_officer')->delete("/reports/schedules/{$s->id}")->assertForbidden();
        $this->as('risk_manager')->delete("/reports/schedules/{$s->id}")->assertSessionHas('success');
    }
}

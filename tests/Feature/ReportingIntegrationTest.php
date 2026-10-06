<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\Kri;
use App\Models\OrgUnit;
use App\Models\Risk;
use App\Models\RiskSnapshot;
use App\Notifications\AlertNotification;
use App\Services\ImportService;
use App\Services\ReportService;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/** Integrasi lintas modul: laporan, AI, impor, audit trail, preferensi notifikasi, snapshot. */
class ReportingIntegrationTest extends TestCase
{
    private function xlsx(array $rows): UploadedFile
    {
        $ss = new Spreadsheet();
        $ss->getActiveSheet()->fromArray($rows, null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'x') . '.xlsx';
        (new Xlsx($ss))->save($path);
        return new UploadedFile($path, 'impor.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function childOfA(): OrgUnit
    {
        $c = OrgUnit::withoutGlobalScopes()->create(['organization_id' => $this->org->id, 'name' => 'Sub A', 'code' => 'A1', 'parent_id' => $this->unitA->id]);
        $c->refreshPath();
        return $c;
    }

    public function test_report_filters_follow_register_semantics(): void
    {
        $child = $this->childOfA();
        $a = $this->makeRisk(['name' => 'Risiko A', 'inherent_l' => 5, 'inherent_i' => 5, 'residual_l' => 5, 'residual_i' => 5]);
        $c = $this->makeRisk(['name' => 'Risiko sub-unit', 'residual_l' => 1, 'residual_i' => 1, 'target_l' => 1, 'target_i' => 1], $child);
        $b = $this->makeRisk(['name' => 'Risiko B'], $this->unitB);
        $closed = $this->makeRisk(['name' => 'Ditutup', 'status' => 'closed']);
        $svc = app(ReportService::class);
        $user = $this->users['risk_manager'];
        $this->actingAs($user);

        $ids = fn (array $p) => $svc->data('register', $user, $p)['risks']->pluck('id')->sort()->values()->all();
        $this->assertSame([$a->id, $c->id], $ids(['unit_id' => $this->unitA->id])); // termasuk sub-unit, tanpa yang ditutup
        $this->assertSame([$a->id], $ids(['level' => 'high,very_high', 'unit_id' => $this->unitA->id]));
        $this->assertSame([$closed->id], $ids(['status' => 'closed']));
        $this->assertSame([$b->id], $ids(['q' => 'Risiko B']));
        $this->assertStringContainsString('Unit A', $svc->filterLabel(['unit_id' => $this->unitA->id, 'level' => 'high']));

        // insiden & KRI juga mengikuti filter unit (bukan hanya untuk pengguna bercakupan)
        Incident::create(['code' => 'INC-A', 'title' => 'A', 'occurred_at' => now(), 'status' => 'reported', 'unit_id' => $child->id]);
        Incident::create(['code' => 'INC-B', 'title' => 'B', 'occurred_at' => now(), 'status' => 'reported', 'unit_id' => $this->unitB->id]);
        Kri::create(['code' => 'KRI-A', 'name' => 'A', 'risk_id' => $a->id, 'source' => 'manual', 'frequency' => 'monthly', 'direction' => 'up_bad', 'threshold_warn' => 1, 'threshold_crit' => 3, 'status' => 'normal']);
        Kri::create(['code' => 'KRI-B', 'name' => 'B', 'risk_id' => $b->id, 'source' => 'manual', 'frequency' => 'monthly', 'direction' => 'up_bad', 'threshold_warn' => 1, 'threshold_crit' => 3, 'status' => 'normal']);
        $this->assertSame(['INC-A'], $svc->data('incidents', $user, ['unit_id' => $this->unitA->id])['incidents']->pluck('code')->all());
        $this->assertSame(['KRI-A'], $svc->data('kri', $user, ['unit_id' => $this->unitA->id])['kris']->pluck('code')->all());

        // HTTP: filter diterima dan disimpan pada riwayat; halaman laporan menerima filter awal dari query string
        $this->post('/reports', ['type' => 'register', 'format' => 'xlsx', 'unit_id' => $this->unitA->id, 'level' => 'high,very_high'])->assertOk();
        $this->assertSame('high,very_high', \App\Models\ReportJob::latest('id')->first()->params['level']);
        $this->post('/reports', ['type' => 'register', 'format' => 'pdf', 'level' => 'bogus'])->assertSessionHasErrors('level');
        $this->get('/reports?type=register&unit_id=' . $this->unitA->id . '&level=high,very_high&evaluation=nope')
            ->assertInertia(fn ($p) => $p->where('initial.type', 'register')->where('initial.level', 'high,very_high')->missing('initial.evaluation'));
    }

    public function test_scoped_user_without_units_sees_nothing_in_reports(): void
    {
        $this->makeRisk();
        $this->makeRisk([], $this->unitB);
        $this->actingAs($this->users['risk_manager']);
        Incident::create(['code' => 'INC-1', 'title' => 'A', 'occurred_at' => now(), 'status' => 'reported', 'unit_id' => $this->unitA->id]);
        $officer = $this->users['risk_officer'];
        $officer->update(['unit_id' => null, 'scope_units' => null]);
        $officer = $officer->fresh();
        $this->assertSame([], $officer->accessibleUnitIds());
        $this->actingAs($officer);
        $d = app(ReportService::class)->data('executive', $officer, []);
        $this->assertCount(0, $d['risks']);
        $this->assertSame(0, $d['incidents_ytd']);
        $this->assertCount(0, app(ReportService::class)->data('incidents', $officer, [])['incidents']);
    }

    public function test_weekly_schedule_day_must_be_1_to_7(): void
    {
        $base = ['type' => 'kri', 'format' => 'pdf', 'recipients' => ['a@contoh.go.id']];
        $this->as('risk_manager')->post('/reports/schedules', $base + ['frequency' => 'weekly', 'day' => 10])->assertSessionHasErrors('day');
        $this->as('risk_manager')->post('/reports/schedules', $base + ['frequency' => 'weekly', 'day' => 5])->assertSessionHas('success');
        $this->as('risk_manager')->post('/reports/schedules', $base + ['frequency' => 'monthly', 'day' => 20])->assertSessionHas('success');
    }

    public function test_ai_summary_is_scoped_to_user_units(): void
    {
        $this->makeRisk();
        $this->actingAs($this->users['risk_manager']);
        Incident::create(['code' => 'INC-A', 'title' => 'A', 'occurred_at' => now(), 'status' => 'reported', 'unit_id' => $this->unitA->id]);
        Incident::create(['code' => 'INC-B1', 'title' => 'B', 'occurred_at' => now(), 'status' => 'reported', 'unit_id' => $this->unitB->id]);
        Incident::create(['code' => 'INC-B2', 'title' => 'B', 'occurred_at' => now(), 'status' => 'reported', 'unit_id' => $this->unitB->id]);
        $r = $this->as('risk_officer')->postJson('/ai', ['feature' => 'summarize'])->assertOk();
        $this->assertStringContainsString('Insiden tahun berjalan: 1 ', $r->json('text'));
        $this->assertArrayHasKey('R-2026-001', $r->json('refs'));
    }

    public function test_import_preview_survives_refresh_and_commit_redirects_with_ids(): void
    {
        $head = array_values(ImportService::RISK_COLUMNS);
        $row = fn ($n) => [$n, 'A', 'Strategis', 'risk_owner@uji.test', '', 'sebab', 'kejadian', 'akibat', 'internal', 'process', '', 4, 4, 3, 3, 2, 2, 'reduce'];
        $r = $this->as('risk_officer')->post('/import/risk/preview', ['file' => $this->xlsx([$head, $row('Impor satu'), $row('Impor dua')])])->assertOk();
        $token = $r->viewData('page')['props']['preview']['token'];
        // muat ulang peramban: GET ke URL pratinjau menampilkan pratinjau yang sama, bukan 405
        $this->get('/import/risk/preview')->assertOk()->assertInertia(fn ($p) => $p->where('preview.token', $token)->has('preview.valid', 2));
        $res = $this->post('/import/risk/commit', ['token' => $token]);
        $ids = Risk::withoutGlobalScopes()->orderBy('id')->pluck('id')->implode(',');
        $res->assertRedirect('/risks?ids=' . urlencode($ids))->assertSessionHas('success', '2 risiko berhasil diimpor sebagai draft.');
        $this->get('/import/risk/preview')->assertRedirect('/import/risk')->assertSessionHas('error'); // token sudah dipakai
    }

    public function test_kri_import_redirect_lists_codes(): void
    {
        $risk = $this->makeRisk();
        $this->actingAs($this->users['risk_manager']);
        Kri::create(['code' => 'KRI-001', 'name' => 'Downtime', 'risk_id' => $risk->id, 'source' => 'manual', 'frequency' => 'monthly', 'direction' => 'up_bad', 'threshold_warn' => 1, 'threshold_crit' => 3, 'status' => 'normal']);
        $r = $this->post('/import/kri/preview', ['file' => $this->xlsx([array_values(ImportService::KRI_COLUMNS), ['KRI-001', '2026-08', 0.5, 'x']])]);
        $this->post('/import/kri/commit', ['token' => $r->viewData('page')['props']['preview']['token']])->assertRedirect('/kris')->assertSessionHas('success', '1 nilai KRI berhasil diimpor untuk 1 KRI: KRI-001.');
    }

    public function test_audit_export_honours_screen_filters(): void
    {
        $log = fn ($type, $label, $action = 'updated') => AuditLog::create(['organization_id' => $this->org->id, 'user_id' => $this->users['risk_manager']->id, 'action' => $action, 'subject_type' => $type, 'subject_id' => 7, 'subject_label' => $label, 'created_at' => now()]);
        $log('risk', 'Risiko pusat data');
        $log('control', 'Kontrol pusat data');
        $log('risk', 'Risiko anggaran');
        $csv = $this->as('auditor')->get('/admin/audit/export?q=pusat&subject=risk')->assertOk()->streamedContent();
        $this->assertStringContainsString('Risiko pusat data', $csv);
        $this->assertStringNotContainsString('Kontrol pusat data', $csv);
        $this->assertStringNotContainsString('Risiko anggaran', $csv);
        $this->as('auditor')->get('/admin/audit?subject=risk')->assertInertia(fn ($p) => $p->where('logs.data.0.kind', 'risk'));
    }

    public function test_alert_notification_channels_follow_email_preference(): void
    {
        $n = new AlertNotification(new \App\Models\Alert(['title' => 'x', 'severity' => 'info']));
        $u = $this->users['risk_owner'];
        $this->assertContains('mail', $n->via($u)); // bawaan: email aktif
        $u->preferences = ['notify' => ['mail']];
        $this->assertContains('mail', $n->via($u));
        foreach ([[], ['database']] as $prefs) {
            $u->preferences = ['notify' => $prefs];
            $this->assertNotContains('mail', $n->via($u));
        }
        $this->as('risk_owner')->put('/profile', ['name' => 'Pemilik', 'preferences' => ['notify' => []]])->assertSessionHas('success');
        $this->assertNotContains('mail', $n->via($this->users['risk_owner']->fresh()));
    }

    public function test_snapshot_skips_closed_and_deleted_risks(): void
    {
        $active = $this->makeRisk();
        $closed = $this->makeRisk(['status' => 'closed']);
        $deleted = $this->makeRisk();
        $deleted->delete();
        $period = now()->format('Y-m');
        RiskSnapshot::withoutGlobalScopes()->create(['organization_id' => $this->org->id, 'risk_id' => $closed->id, 'period' => $period, 'inherent_score' => 1, 'residual_score' => 1, 'level' => 'low', 'status' => 'monitoring', 'created_at' => now()]);
        $this->artisan('manrisk:snapshot')->assertSuccessful();
        $this->assertSame([$active->id], RiskSnapshot::withoutGlobalScopes()->where('period', $period)->pluck('risk_id')->all());
    }
}

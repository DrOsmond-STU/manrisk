<?php

namespace Tests\Feature;

use App\Models\CriteriaVersion;
use App\Models\Risk;
use App\Models\RiskCategory;
use App\Support\RiskLimits;
use App\Support\Scoring;

/** Penyesuaian dengan Permen BUMN PER-2/MBU/03/2023. */
class BumnComplianceTest extends \Tests\TestCase
{
    private function category(array $attrs): RiskCategory
    {
        $c = new RiskCategory($attrs + ['appetite' => 6, 'tolerance' => 9]);
        $c->organization_id = $this->org->id;
        $c->save();
        return $c;
    }

    public function test_bumn_criteria_scheme_recalculates_five_levels(): void
    {
        $risk = $this->makeRisk(['residual_l' => 2, 'residual_i' => 3]); // skor 6
        $current = CriteriaVersion::withoutGlobalScopes()->where('organization_id', $this->org->id)->where('active', true)->first();
        $payload = $current->only('likelihood', 'impact', 'dimensions', 'thresholds') + ['effective_from' => now()->toDateString(), 'scheme' => 'bumn', 'matrix' => Scoring::defaultMatrix('bumn'), 'activate' => true];
        $this->as('risk_admin')->post('/criteria', $payload)->assertSessionHas('success');
        $this->assertSame('low_medium', $risk->fresh()->residual_level);
        $this->as('risk_admin')->get('/dashboard')->assertInertia(fn ($p) => $p->where('labels.level_scheme', 'bumn')->where('labels.levels.low_medium', 'Low to Moderate')->where('labels.levels.very_high', 'High'));
        // level 5 tingkat dapat dipakai sebagai filter register
        $this->as('risk_admin')->get('/risks?level=low_medium')->assertInertia(fn ($p) => $p->where('risks.total', 1));
        // matriks skema BUMN menolak kunci di luar skemanya
        $bad = $payload;
        $bad['matrix']['1-1'] = 'nonsense';
        $this->as('risk_admin')->post('/criteria', $bad)->assertSessionHasErrors('matrix.1-1');
    }

    public function test_taxonomy_three_tiers_and_aggregated_filter(): void
    {
        $t1 = $this->category(['name' => 'Risiko Bisnis BUMN', 'bumn_code' => 'T1.02']);
        $t2 = $this->category(['name' => 'Risiko Operasional', 'parent_id' => $t1->id]);
        $t3 = $this->category(['name' => 'Gangguan sistem TI', 'parent_id' => $t2->id]);
        $this->makeRisk(['category_id' => $t3->id]);
        $tree = RiskCategory::withoutGlobalScopes()->where('organization_id', $this->org->id)->get();
        $node = RiskCategory::tree($tree)->firstWhere('id', $t3->id);
        $this->assertSame(3, $node->depth);
        $this->assertSame('Risiko Bisnis BUMN › Risiko Operasional › Gangguan sistem TI', $node->path);
        // memilih T1 menampilkan risiko di T3 turunannya
        $this->as('risk_admin')->get("/risks?category_id={$t1->id}")->assertInertia(fn ($p) => $p->where('risks.total', 1));
        // tingkat keempat ditolak
        $this->as('risk_admin')->post('/criteria/categories', ['name' => 'T4', 'parent_id' => $t3->id, 'appetite' => 6, 'tolerance' => 9])->assertSessionHasErrors('parent_id');
        // memindahkan T1 (pohon 3 tingkat) ke bawah kategori lain juga ditolak
        $other = $this->category(['name' => 'Lain']);
        $this->as('risk_admin')->put("/criteria/categories/{$t1->id}", ['name' => 'Risiko Bisnis BUMN', 'parent_id' => $other->id, 'appetite' => 6, 'tolerance' => 9])->assertSessionHasErrors('parent_id');
        $this->as('risk_admin')->post('/criteria/categories', ['name' => 'Kategori baru', 'parent_id' => $t1->id, 'bumn_code' => 'T2.09', 'risk_limit' => 5000000, 'appetite' => 6, 'tolerance' => 9])->assertSessionHas('success');
        $this->as('risk_admin')->get('/criteria')->assertInertia(fn ($p) => $p->where('categories', fn ($cats) => collect($cats)->firstWhere('id', $t1->id)['risks_total'] === 1));
    }

    public function test_exposure_quarter_targets_and_risk_limits(): void
    {
        $this->as('risk_officer')->post('/risks', $this->riskPayload(['inherent_exposure' => 100, 'residual_exposure' => 200]))->assertSessionHasErrors('residual_exposure');
        $this->as('risk_officer')->post('/risks', $this->riskPayload([
            'inherent_exposure' => 5000000000, 'residual_exposure' => 2000000000, 'target_exposure' => 500000000,
            'quarter_targets' => [['quarter' => 1, 'target_l' => 3, 'target_i' => 3, 'target_exposure' => 1500000000], ['quarter' => 4, 'target_l' => 2, 'target_i' => 3]],
        ]))->assertSessionHas('success');
        $risk = Risk::withoutGlobalScopes()->latest('id')->first();
        $this->assertEquals(2000000000, $risk->residual_exposure);
        $this->assertSame([1, 4], $risk->quarterTargets()->pluck('quarter')->all());
        $this->assertSame(9, $risk->quarterTargets()->where('quarter', 1)->value('target_score'));
        $this->as('risk_officer')->get("/risks/{$risk->id}")->assertInertia(fn ($p) => $p->where('quarters.year', now()->year)->has('quarters.rows', 4));
        // risk limit kategori dan organisasi
        $risk->category->update(['risk_limit' => 2500000000]);
        $usage = RiskLimits::usage(Risk::withoutGlobalScopes()->where('organization_id', $this->org->id)->get())->firstWhere('id', $risk->category_id);
        $this->assertSame('near', $usage['status']);
        $this->assertEquals(80.0, $usage['pct']);
        $total = RiskLimits::total(collect([$risk]), ['risk_limit_total' => 1000000000]);
        $this->assertSame('breach', $total['status']);
        $this->as('risk_manager')->get('/risks/evaluation')->assertOk()->assertInertia(fn ($p) => $p->has('limits')->where('total.exposure', fn ($v) => (float) $v === 2000000000.0));
    }

    public function test_board_role_is_read_only_oversight(): void
    {
        $risk = $this->makeRisk();
        $this->as('board')->get('/dashboard/executive')->assertOk();
        $this->as('board')->get('/admin/audit')->assertOk();
        $this->as('board')->get("/risks/{$risk->id}")->assertOk();
        $this->as('board')->post('/risks', $this->riskPayload())->assertForbidden();
        $this->as('board')->get('/admin/users')->assertForbidden();
        $this->as('board')->get('/dashboard')->assertInertia(fn ($p) => $p->where('labels.role_lines.auditor', 'Lini 3 · Audit internal (SPI)'));
    }

    public function test_organization_bumn_settings(): void
    {
        $this->as('super_admin')->put('/settings/organization', ['name' => 'PT Uji (Persero)', 'code' => 'UJI', 'settings' => ['risk_intensity' => 'systemic', 'entity_type' => 'holding', 'committees' => ['audit', 'risk'], 'risk_strategy' => 'Strategi', 'risk_capacity' => 9000000000, 'risk_limit_total' => 6000000000]])->assertSessionHas('success');
        $s = $this->org->fresh()->settings;
        $this->assertSame('systemic', $s['risk_intensity']);
        $this->assertSame(['audit', 'risk'], $s['committees']);
        $this->as('super_admin')->put('/settings/organization', ['name' => 'X', 'code' => 'UJI', 'settings' => ['risk_intensity' => 'bogus']])->assertSessionHasErrors('settings.risk_intensity');
    }

    public function test_quarterly_report_pdf_and_excel(): void
    {
        $risk = $this->makeRisk(['inherent_exposure' => 3000000, 'residual_exposure' => 1000000]);
        \App\Models\RiskQuarterTarget::create(['risk_id' => $risk->id, 'year' => now()->year, 'quarter' => now()->quarter, 'target_l' => 3, 'target_i' => 3, 'target_score' => 9]);
        $ap = new \App\Models\ActionPlan(['code' => 'AP-1', 'risk_id' => $risk->id, 'title' => 'Mitigasi', 'pic_id' => $this->users['risk_owner']->id, 'due_date' => now()->addMonth(), 'budget' => 5000000, 'actual_cost' => 2500000]);
        $ap->organization_id = $this->org->id;
        $ap->save();
        $data = app(\App\Services\ReportService::class)->data('quarterly', $this->users['risk_manager'], ['quarter' => now()->quarter, 'year' => now()->year]);
        $this->assertStringContainsString('Triwulan ' . now()->quarter, $data['title']);
        $this->assertSame(1, $data['targets_met']);
        $this->assertEquals(2500000, $data['plan_cost']);
        $pdf = $this->as('risk_manager')->post('/reports', ['type' => 'quarterly', 'format' => 'pdf', 'quarter' => now()->quarter, 'year' => now()->year]);
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $xlsx = $this->as('risk_manager')->post('/reports', ['type' => 'quarterly', 'format' => 'xlsx', 'quarter' => now()->quarter]);
        $xlsx->assertOk();
        $this->assertStringStartsWith('PK', $xlsx->getContent());
    }

    public function test_rmi_assessment_flow(): void
    {
        $this->as('risk_admin')->post('/rmi', ['year' => now()->year, 'type' => 'internal'])->assertSessionHas('error');
        $this->as('risk_admin')->post('/rmi-parameters', ['dimension' => 'culture', 'code' => 'BR-1', 'name' => 'Tone at the top', 'weight' => 2])->assertSessionHas('success');
        $this->as('risk_admin')->post('/rmi-parameters', ['dimension' => 'process', 'code' => 'PR-1', 'name' => 'Proses identifikasi risiko', 'weight' => 1])->assertSessionHas('success');
        $this->as('risk_admin')->post('/rmi-parameters', ['dimension' => 'process', 'code' => 'PR-1', 'name' => 'Duplikat', 'weight' => 1])->assertSessionHasErrors('code');
        $this->as('risk_owner')->post('/rmi', ['year' => now()->year, 'type' => 'internal'])->assertForbidden();
        $this->as('risk_manager')->post('/rmi', ['year' => now()->year, 'type' => 'internal', 'assessor' => 'Tim MR'])->assertRedirect();
        $a = \App\Models\RmiAssessment::withoutGlobalScopes()->latest('id')->first();
        $this->assertSame(2, $a->scores()->count());
        $scores = $a->scores()->with('parameter')->get();
        $this->as('risk_manager')->post("/rmi/{$a->id}/finalize")->assertSessionHas('error');
        $payload = ['scores' => $scores->map(fn ($s) => ['id' => $s->id, 'score' => $s->parameter->code === 'BR-1' ? 4 : 1, 'evidence' => 'Bukti'])->all()];
        $this->as('risk_manager')->put("/rmi/{$a->id}/scores", $payload)->assertSessionHas('success');
        $this->assertEquals(3.0, $a->fresh()->score); // (4*2 + 1*1) / 3
        $this->as('risk_manager')->get("/rmi/{$a->id}")->assertInertia(fn ($p) => $p->component('Rmi/Show')->where('result.dimensions.culture', 4)->where('can.edit', true));
        $this->as('risk_manager')->post("/rmi/{$a->id}/finalize")->assertSessionHas('success');
        $a->refresh();
        $this->assertSame('final', $a->status);
        $this->assertSame(\App\Models\RmiAssessment::phaseFor(3.0), $a->phase);
        // final tidak dapat diubah atau dihapus
        $this->as('risk_manager')->put("/rmi/{$a->id}/scores", $payload)->assertStatus(422);
        $this->as('risk_admin')->delete("/rmi/{$a->id}")->assertStatus(422);
        // parameter yang dipakai penilaian final dinonaktifkan, bukan dihapus
        $param = \App\Models\RmiParameter::withoutGlobalScopes()->where('code', 'BR-1')->first();
        $this->as('risk_admin')->delete("/rmi-parameters/{$param->id}")->assertSessionHas('success');
        $this->assertFalse($param->fresh()->active);
        $this->as('board')->get('/rmi')->assertOk()->assertInertia(fn ($p) => $p->where('latest.index', 3)->where('compliance.internal_this_year.status', 'final')->where('compliance.independent_due', true)->where('can.write', false));
        $pdf = $this->as('board')->get("/rmi/{$a->id}/pdf");
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_kks_risk_assessment(): void
    {
        $this->as('risk_manager')->post('/context/scopes', ['name' => 'Akuisisi PT X', 'kind' => 'kks'])->assertSessionHasErrors('kks_type');
        $this->as('risk_manager')->post('/context/scopes', ['name' => 'Akuisisi PT X', 'kind' => 'kks', 'kks_type' => 'acquisition', 'transaction_value' => 750000000000, 'decision_organ' => 'rups', 'decision_date' => now()->toDateString(), 'conclusion' => 'Layak dengan mitigasi.'])->assertSessionHas('success');
        $scope = \App\Models\Scope::withoutGlobalScopes()->latest('id')->first();
        $this->assertSame('kks', $scope->kind);
        $this->as('risk_officer')->post('/risks', $this->riskPayload(['scope_id' => $scope->id, 'inherent_exposure' => 90000000000, 'residual_exposure' => 30000000000]))->assertSessionHas('success');
        $this->as('risk_officer')->get("/risks?scope_id={$scope->id}")->assertInertia(fn ($p) => $p->where('risks.total', 1));
        $this->as('risk_officer')->get('/context')->assertInertia(fn ($p) => $p->where('scopes.0.risks_count', 1)->has('kks_types'));
        $this->as('risk_officer')->get("/risks/create?scope_id={$scope->id}")->assertInertia(fn ($p) => $p->where('prefill.scope_id', (string) $scope->id));
        $pdf = $this->as('board')->get("/context/scopes/{$scope->id}/kks-pdf");
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        // ruang lingkup biasa tidak menyimpan atribut KKS dan tidak punya kajian PDF
        $this->as('risk_manager')->put("/context/scopes/{$scope->id}", ['name' => 'Ruang lingkup', 'kks_type' => 'acquisition'])->assertSessionHas('success');
        $this->assertNull($scope->fresh()->kks_type);
        $this->as('risk_manager')->get("/context/scopes/{$scope->id}/kks-pdf")->assertNotFound();
    }
}

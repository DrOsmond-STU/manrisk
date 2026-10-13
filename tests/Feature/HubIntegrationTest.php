<?php

namespace Tests\Feature;

use App\Models\ActionPlan;
use App\Models\Alert;
use App\Models\Approval;
use App\Models\Kri;
use App\Models\Review;
use App\Models\Risk;
use App\Services\AlertService;
use App\Services\ApprovalService;
use App\Support\Numbering;
use Tests\TestCase;

/** Keterkaitan antarmenu: risk register ↔ persetujuan, review, organisasi, dashboard, peringatan. */
class HubIntegrationTest extends TestCase
{
    private function plan(Risk $risk, array $attrs = []): ActionPlan
    {
        $p = new ActionPlan(array_merge(['code' => 'AP-T-' . uniqid(), 'risk_id' => $risk->id, 'title' => 'Rencana', 'pic_id' => $this->users['risk_owner']->id, 'unit_id' => $risk->unit_id,
            'priority' => 'medium', 'start_date' => now(), 'due_date' => now()->addDays(10), 'progress' => 0], $attrs));
        $p->organization_id = $this->org->id;
        $p->save();
        return $p;
    }

    public function test_rejected_score_change_restores_previous_scores_and_status(): void
    {
        $risk = $this->makeRisk(['status' => 'monitoring', 'residual_l' => 2, 'residual_i' => 2]);
        $this->as('risk_officer')->put("/risks/{$risk->id}", $this->riskPayload(['name' => $risk->name, 'category_id' => $risk->category_id, 'inherent_l' => 5, 'inherent_i' => 5, 'residual_l' => 4, 'residual_i' => 5, 'target_l' => 2, 'target_i' => 2, 'note' => 'naik']))->assertSessionHasNoErrors()->assertRedirect();
        $risk->refresh();
        $this->assertSame('pending', $risk->status);
        $this->assertSame(20, $risk->residual_score);
        $approval = Approval::where('subject_id', $risk->id)->latest('id')->first();
        $this->assertSame('monitoring', $approval->payload['prev_status']);

        $this->actingAs($this->users['risk_owner']);
        app(ApprovalService::class)->decide($approval, $this->users['risk_owner'], 'reject', 'tidak didukung data');
        $risk->refresh();
        $this->assertSame(4, $risk->residual_score); // kembali ke nilai sebelum diajukan
        $this->assertSame('monitoring', $risk->status);
        $this->assertStringContainsString('Ditolak', $risk->versions()->where('version', $risk->version)->value('note'));
        // pengaju menerima pemberitahuan hasil
        $this->assertDatabaseHas('alerts', ['type' => 'approval_result', 'subject_id' => $approval->id]);
    }

    public function test_rejected_or_revised_new_risk_stays_draft(): void
    {
        foreach (['reject', 'revise'] as $action) {
            $risk = $this->makeRisk(['status' => 'draft', 'treatment' => 'retain', 'residual_l' => 2, 'residual_i' => 2]);
            $this->actingAs($this->users['risk_officer']);
            $a = app(ApprovalService::class)->submit($risk, 'retain', $this->users['risk_officer'], 'terima');
            app(ApprovalService::class)->decide($a, $this->users['risk_owner'], $action, 'belum');
            $this->assertSame('draft', $risk->fresh()->status, $action);
        }
    }

    public function test_approval_request_notifies_approver_and_focus_link_works(): void
    {
        $risk = $this->makeRisk(['status' => 'draft', 'residual_l' => 2, 'residual_i' => 2]);
        // dua calon penyetuju → memastikan unit pengguna dimuat sekaligus (tanpa lazy load)
        \App\Models\User::withoutGlobalScopes()->create(['organization_id' => $this->org->id, 'name' => 'Owner 2', 'email' => 'owner2@uji.test', 'password' => 'Secret#Pass123', 'role' => 'risk_owner', 'unit_id' => $this->unitB->id]);
        $this->as('risk_officer')->post("/risks/{$risk->id}/submit")->assertSessionHas('success');
        $a = Approval::where('subject_id', $risk->id)->first();
        $alert = Alert::where('type', 'approval_request')->where('subject_id', $a->id)->first();
        $this->assertNotNull($alert);
        $this->assertSame("/approvals?id={$a->id}", $alert->link);
        $this->as('risk_owner')->get("/approvals?id={$a->id}")->assertInertia(fn ($p) => $p->where('focus.items.0.id', $a->id));
        $this->as('risk_owner')->get("/approvals?risk_id={$risk->id}")->assertInertia(fn ($p) => $p->has('focus.items', 1));
        // keputusan menyelesaikan peringatan tahap tsb
        $this->as('risk_owner')->post("/approvals/{$a->id}/decide", ['action' => 'approve']);
        $this->assertNotNull($alert->fresh()->handled_at);
    }

    public function test_closed_risk_cannot_be_edited(): void
    {
        $risk = $this->makeRisk(['status' => 'closed']);
        $this->as('risk_manager')->get("/risks/{$risk->id}/edit")->assertRedirect("/risks/{$risk->id}");
        $this->as('risk_manager')->put("/risks/{$risk->id}", $this->riskPayload(['residual_l' => 3, 'residual_i' => 3]))->assertSessionHas('error');
        $this->as('risk_manager')->get("/risks/{$risk->id}")->assertInertia(fn ($p) => $p->where('can.edit', false));
    }

    public function test_register_accepts_level_and_evaluation_lists_and_ids(): void
    {
        $hi = $this->makeRisk(['residual_l' => 3, 'residual_i' => 4]);   // 12 high
        $vh = $this->makeRisk(['residual_l' => 4, 'residual_i' => 5]);   // 20 very_high
        $lo = $this->makeRisk(['residual_l' => 1, 'residual_i' => 2]);
        $this->as('risk_manager')->get('/risks?level=high,very_high')->assertInertia(fn ($p) => $p->where('risks.total', 2));
        $this->as('risk_manager')->get('/risks?level=high,bogus')->assertSessionHasErrors('level');
        $this->as('risk_manager')->get("/risks?ids={$lo->id},{$vh->id}")->assertInertia(fn ($p) => $p->where('risks.total', 2));
        $this->as('risk_manager')->get('/risks?evaluation=escalate,critical')->assertOk();
    }

    public function test_create_prefill_from_objective_process_and_ai(): void
    {
        $this->actingAs($this->users['super_admin']);
        $proc = \App\Models\Process::create(['name' => 'Proses uji', 'unit_id' => $this->unitA->id]);
        $this->as('risk_officer')->get("/risks/create?process_id={$proc->id}&name=Server+down")
            ->assertInertia(fn ($p) => $p->where('prefill.process_id', (string) $proc->id)->where('prefill.unit_id', $this->unitA->id)->where('prefill.name', 'Server down'));
    }

    public function test_deleting_draft_cleans_up_dependants(): void
    {
        $risk = $this->makeRisk(['status' => 'draft']);
        $plan = $this->plan($risk);
        $this->actingAs($this->users['super_admin']);
        $kri = Kri::create(['code' => 'KRI-T1', 'name' => 'Uji', 'risk_id' => $risk->id, 'threshold_warn' => 5, 'threshold_crit' => 10]);
        $this->as('risk_manager')->delete("/risks/{$risk->id}")->assertRedirect('/risks');
        $this->assertSoftDeleted($plan);
        $this->assertNull($kri->fresh()->risk_id);
    }

    public function test_review_close_requires_finished_plans_and_quarter_counts_any_period_type(): void
    {
        $risk = $this->makeRisk(['status' => 'monitoring']);
        $this->plan($risk);
        $this->as('risk_manager')->post('/reviews', ['risk_id' => $risk->id, 'period_type' => 'adhoc', 'period' => 'ad hoc', 'decision' => 'close'])->assertSessionHas('warning');
        $this->assertSame(0, Approval::where('subject_id', $risk->id)->count());
        // review ad hoc dalam triwulan berjalan → tidak lagi muncul di "perlu direview"
        $this->as('risk_manager')->get('/reviews')->assertInertia(fn ($p) => $p->where('due', fn ($due) => collect($due)->doesntContain('id', $risk->id)));
    }

    public function test_unit_in_use_cannot_be_deleted_and_counts_include_children(): void
    {
        $this->actingAs($this->users['super_admin']);
        $child = \App\Models\OrgUnit::create(['name' => 'Sub A', 'code' => 'A1', 'parent_id' => $this->unitA->id, 'type' => 'unit']);
        $child->refreshPath();
        $this->makeRisk([], $child);
        $this->as('super_admin')->get('/organization/units')->assertInertia(fn ($p) => $p->where('units', fn ($u) => collect($u)->firstWhere('id', $this->unitA->id)['risks_total'] === 1));
        $empty = \App\Models\OrgUnit::create(['name' => 'Kosong', 'code' => 'K1', 'type' => 'unit']);
        $this->users['auditor']->forceFill(['unit_id' => $empty->id])->save();
        $this->as('super_admin')->delete("/organization/units/{$empty->id}")->assertSessionHas('error');
        $this->assertNotSoftDeleted($empty);
    }

    public function test_matrix_and_evaluation_follow_unit_filter(): void
    {
        $this->makeRisk([], $this->unitA);
        $this->makeRisk([], $this->unitB);
        $this->as('risk_manager')->get("/risks/matrix?unit_id={$this->unitA->id}")->assertInertia(fn ($p) => $p->has('risks', 1)->where('filters.unit_id', (string) $this->unitA->id));
        $this->as('risk_manager')->get("/risks/evaluation?unit_id={$this->unitB->id}")->assertInertia(fn ($p) => $p->has('risks', 1));
    }

    public function test_dashboard_groups_carry_ids_and_pending_counts_actionable_only(): void
    {
        $risk = $this->makeRisk(['status' => 'draft', 'residual_l' => 2, 'residual_i' => 2]);
        $this->actingAs($this->users['risk_officer']);
        app(ApprovalService::class)->submit($risk, 'new_risk', $this->users['risk_officer']);
        $this->as('risk_owner')->get('/dashboard')->assertInertia(fn ($p) => $p->where('kpi.pending_approvals', 1));
        $this->as('management')->get('/dashboard')->assertInertia(fn ($p) => $p->where('kpi.pending_approvals', 0));
        $this->makeRisk();
        $this->as('risk_manager')->get('/dashboard')->assertInertia(fn ($p) => $p->has('by_category.0.id')->has('by_unit.0.id'));
    }

    public function test_alert_open_marks_read_and_redirects_only_internally(): void
    {
        $risk = $this->makeRisk();
        $this->actingAs($this->users['risk_manager']);
        $ok = app(AlertService::class)->raise('score_up', 'warning', $risk, 'Naik', null, "/risks/{$risk->id}");
        $evil = app(AlertService::class)->raise('score_up', 'warning', $risk, 'Jahat', null, 'https://evil.example/x');
        $this->get("/alerts/{$ok->id}/open")->assertRedirect("/risks/{$risk->id}");
        $this->assertNotNull($ok->fresh()->read_at);
        $this->get("/alerts/{$evil->id}/open")->assertRedirect('/alerts');
        app(AlertService::class)->raise('score_up', 'info', $risk, 'Belum dibaca', null, "/risks/{$risk->id}");
        $this->get('/alerts?unread=1')->assertInertia(fn ($p) => $p->where('unread', 1)->has('alerts.data', 1));
    }

    public function test_daily_sweep_links_to_detail_and_skips_closed_or_deleted(): void
    {
        $active = $this->makeRisk();
        $closed = $this->makeRisk(['status' => 'closed']);
        $late = $this->plan($active, ['due_date' => now()->subDays(3)]);
        $this->plan($closed, ['due_date' => now()->subDays(3)]);
        $gone = $this->plan($active, ['due_date' => now()->subDays(3)]);
        $gone->delete();
        app(AlertService::class)->dailySweep($this->org->id);
        $alerts = Alert::where('type', 'action_overdue')->get();
        $this->assertCount(1, $alerts);
        $this->assertSame("/action-plans/{$late->id}", $alerts->first()->link);
    }

    public function test_background_duplicate_check_does_not_consume_flash(): void
    {
        $this->as('risk_officer')->withSession(['_flash' => ['new' => [], 'old' => ['success']], 'success' => 'Risiko tersimpan'])
            ->getJson("/risks/similar?unit_id={$this->unitA->id}&text=" . urlencode('teks yang cukup panjang'))->assertOk()->assertSessionHas('success', 'Risiko tersimpan');
    }

    public function test_super_admin_still_bound_by_status_rules(): void
    {
        $active = $this->makeRisk(['status' => 'treating']);
        $sa = $this->users['super_admin'];
        $this->assertFalse($sa->can('submit', $active)); // risiko aktif tidak boleh diajukan ulang sebagai risiko baru
        $this->assertFalse($sa->can('close', $this->makeRisk(['status' => 'closed'])));
        $this->assertTrue($sa->can('submit', $this->makeRisk(['status' => 'draft'])));
        $draft = $this->makeRisk(['status' => 'draft', 'residual_l' => 2, 'residual_i' => 2]);
        $this->actingAs($this->users['risk_officer']);
        $a = app(ApprovalService::class)->submit($draft, 'new_risk', $this->users['risk_officer']);
        $this->assertTrue($sa->can('decide', $a->fresh()));
        app(ApprovalService::class)->decide($a->fresh(), $sa, 'approve');
        app(ApprovalService::class)->decide($a->fresh(), $sa, 'approve');
        $this->assertSame('approved', $a->fresh()->status);
        $this->assertFalse($sa->can('decide', $a->fresh())); // sudah diputus → tombol tidak tampil
        $own = app(ApprovalService::class)->submit($this->makeRisk(['status' => 'draft']), 'new_risk', $sa);
        $this->assertFalse($sa->can('decide', $own->fresh())); // pengajuan sendiri
    }

    public function test_numbering_continues_past_999(): void
    {
        $this->actingAs($this->users['risk_manager']);
        $year = now()->format('Y');
        $this->makeRisk(['code' => "R-$year-999"]);
        $this->makeRisk(['code' => "R-$year-1000"]);
        $this->assertSame("R-$year-1001", Numbering::next(Risk::class, 'R'));
    }
}

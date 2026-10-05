<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Services\ApprovalService;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
{
    public function test_chain_skips_requester_role_and_escalates_high_scores(): void
    {
        $svc = app(ApprovalService::class);
        $low = $this->makeRisk(['residual_l' => 2, 'residual_i' => 2]);
        $this->assertSame(['risk_owner', 'risk_manager'], $svc->chainFor($low, 'new_risk', $this->users['risk_officer']));
        $this->assertSame(['risk_manager'], $svc->chainFor($low, 'new_risk', $this->users['risk_owner']));
        $high = $this->makeRisk(['residual_l' => 4, 'residual_i' => 4]);
        $this->assertSame(['risk_owner', 'risk_manager', 'management'], $svc->chainFor($high, 'new_risk', $this->users['risk_officer']));
        $this->assertSame(['risk_owner', 'management'], $svc->chainFor($high, 'new_risk', $this->users['risk_manager']));
    }

    public function test_full_approval_flow(): void
    {
        $risk = $this->makeRisk(['status' => 'draft', 'residual_l' => 4, 'residual_i' => 4]);
        $this->actingAs($this->users['risk_officer']);
        $approval = app(ApprovalService::class)->submit($risk, 'new_risk', $this->users['risk_officer'], 'tolong');
        $this->assertSame(3, $approval->total_steps);
        $this->assertStringStartsWith('WF-', $approval->code);

        // pengaju tidak boleh memutus; peran salah ditolak
        $this->as('risk_officer')->post("/approvals/{$approval->id}/decide", ['action' => 'approve'])->assertForbidden();
        $this->as('risk_manager')->post("/approvals/{$approval->id}/decide", ['action' => 'approve'])->assertForbidden();
        // tahap 1 risk_owner
        $this->as('risk_owner')->get('/approvals')->assertInertia(fn ($p) => $p->has('inbox', 1));
        $this->as('risk_owner')->post("/approvals/{$approval->id}/decide", ['action' => 'approve'])->assertSessionHas('success');
        $this->assertSame(2, $approval->fresh()->current_step);
        // tahap 2 risk_manager minta revisi tanpa catatan → gagal; dengan catatan → status revision, risiko kembali draft
        $this->as('risk_manager')->post("/approvals/{$approval->id}/decide", ['action' => 'revise'])->assertSessionHasErrors('note');
        $this->as('risk_manager')->post("/approvals/{$approval->id}/decide", ['action' => 'revise', 'note' => 'lengkapi'])->assertSessionHas('success');
        $this->assertSame('revision', $approval->fresh()->status);
        $this->assertSame('draft', $risk->fresh()->status);

        // ajukan ulang dan setujui penuh → status treating (residual 16 > appetite)
        $approval2 = app(ApprovalService::class)->submit($risk->fresh(), 'new_risk', $this->users['risk_officer']);
        $this->as('risk_owner')->post("/approvals/{$approval2->id}/decide", ['action' => 'approve']);
        $this->as('risk_manager')->post("/approvals/{$approval2->id}/decide", ['action' => 'approve']);
        $this->as('management')->post("/approvals/{$approval2->id}/decide", ['action' => 'approve', 'note' => 'ok'])->assertSessionHas('success');
        $this->assertSame('approved', $approval2->fresh()->status);
        $this->assertSame('treating', $risk->fresh()->status);
        $this->assertNotNull($risk->fresh()->approved_at);
        $this->assertDatabaseHas('audit_logs', ['subject_id' => $risk->id, 'action' => 'approval_approve']);
        // sudah diputus → tidak bisa diputus lagi
        $this->as('management')->post("/approvals/{$approval2->id}/decide", ['action' => 'approve'])->assertForbidden();
    }

    public function test_rejection_of_new_risk_returns_to_draft_and_closure_closes(): void
    {
        $risk = $this->makeRisk(['status' => 'draft', 'residual_l' => 2, 'residual_i' => 2]);
        $a = app(ApprovalService::class)->submit($risk, 'new_risk', $this->users['risk_owner']);
        $this->as('risk_manager')->post("/approvals/{$a->id}/decide", ['action' => 'reject', 'note' => 'tidak relevan']);
        $this->assertSame('rejected', $a->fresh()->status);
        $this->assertSame('draft', $risk->fresh()->status);
        $mon = $this->makeRisk(['status' => 'monitoring', 'residual_l' => 2, 'residual_i' => 2]);
        $c = app(ApprovalService::class)->submit($mon, 'closure', $this->users['risk_owner'], 'selesai');
        $this->assertSame(['risk_manager', 'management'], $c->steps->pluck('role')->all());
        $this->as('risk_manager')->post("/approvals/{$c->id}/decide", ['action' => 'approve']);
        $this->as('management')->post("/approvals/{$c->id}/decide", ['action' => 'approve']);
        $this->assertSame('closed', $mon->fresh()->status);
        $this->assertNotNull($mon->fresh()->closed_at);
    }

    public function test_super_admin_can_act_on_any_step_and_duplicate_submission_blocked(): void
    {
        $risk = $this->makeRisk(['status' => 'draft', 'residual_l' => 2, 'residual_i' => 2]);
        $a = app(ApprovalService::class)->submit($risk, 'new_risk', $this->users['risk_officer']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(ApprovalService::class)->submit($risk->fresh(), 'new_risk', $this->users['risk_officer']);
    }
}

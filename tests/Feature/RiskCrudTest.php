<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\Risk;
use Tests\TestCase;

class RiskCrudTest extends TestCase
{
    public function test_officer_can_create_risk_with_auto_code_and_scoring(): void
    {
        $r = $this->as('risk_officer')->post('/risks', $this->riskPayload());
        $risk = Risk::withoutGlobalScopes()->first();
        $r->assertRedirect("/risks/{$risk->id}");
        $this->assertSame('R-' . now()->year . '-001', $risk->code);
        $this->assertSame(20, $risk->inherent_score);
        $this->assertSame(12, $risk->residual_score);
        $this->assertSame('high', $risk->residual_level);
        $this->assertSame('draft', $risk->status);
        $this->assertSame($this->org->id, $risk->organization_id);
        $this->assertDatabaseHas('risk_versions', ['risk_id' => $risk->id, 'version' => 1]);
        $this->assertDatabaseHas('audit_logs', ['subject_type' => 'risk', 'subject_id' => $risk->id, 'action' => 'created']);
        $this->as('risk_officer')->post('/risks', $this->riskPayload());
        $this->assertSame('R-' . now()->year . '-002', Risk::withoutGlobalScopes()->orderByDesc('id')->first()->code);
    }

    public function test_validation_rejects_residual_above_inherent(): void
    {
        $this->as('risk_officer')->post('/risks', $this->riskPayload(['inherent_l' => 2, 'inherent_i' => 2, 'residual_l' => 3, 'residual_i' => 3]))->assertSessionHasErrors('residual_l');
        $this->as('risk_officer')->post('/risks', $this->riskPayload(['name' => '', 'category_id' => 999]))->assertSessionHasErrors(['name', 'category_id']);
    }

    public function test_read_only_roles_cannot_write(): void
    {
        foreach (['management', 'auditor'] as $role) {
            $this->as($role)->post('/risks', $this->riskPayload())->assertForbidden();
            $this->as($role)->get('/risks')->assertOk();
        }
    }

    public function test_unit_scope_restricts_officer(): void
    {
        $inB = $this->makeRisk([], $this->unitB);
        $inA = $this->makeRisk([], $this->unitA);
        $this->as('risk_officer')->get("/risks/{$inB->id}")->assertForbidden();
        $this->as('risk_officer')->get("/risks/{$inA->id}")->assertOk();
        $this->as('risk_officer')->post('/risks', $this->riskPayload(['unit_id' => $this->unitB->id]))->assertSessionHasErrors('unit_id');
        $this->as('risk_officer')->get('/risks')->assertInertia(fn ($p) => $p->where('risks.total', 1));
        $this->as('risk_manager')->get('/risks')->assertInertia(fn ($p) => $p->where('risks.total', 2));
    }

    public function test_update_edit_show_and_delete_rules(): void
    {
        $risk = $this->makeRisk(['status' => 'draft']);
        $this->as('risk_owner')->get("/risks/{$risk->id}/edit")->assertOk();
        $this->as('risk_owner')->put("/risks/{$risk->id}", $this->riskPayload(['name' => 'Diubah', 'inherent_l' => 4, 'inherent_i' => 4, 'residual_l' => 3, 'residual_i' => 3, 'target_l' => 2, 'target_i' => 2]))->assertRedirect("/risks/{$risk->id}");
        $this->assertSame('Diubah', $risk->fresh()->name);
        $this->assertDatabaseHas('audit_logs', ['subject_id' => $risk->id, 'action' => 'updated']);
        $this->as('risk_owner')->delete("/risks/{$risk->id}")->assertForbidden(); // owner bukan deleter
        $this->as('risk_manager')->delete("/risks/{$risk->id}")->assertRedirect('/risks');
        $this->assertSoftDeleted('risks', ['id' => $risk->id]);
        $active = $this->makeRisk(['status' => 'monitoring']);
        $this->as('risk_manager')->delete("/risks/{$active->id}")->assertSessionHas('error');
    }

    public function test_score_change_on_approved_risk_creates_version_and_approval(): void
    {
        $risk = $this->makeRisk(['status' => 'monitoring']);
        $this->as('risk_officer')->put("/risks/{$risk->id}", $this->riskPayload(['inherent_l' => 5, 'inherent_i' => 5, 'residual_l' => 4, 'residual_i' => 4, 'target_l' => 2, 'target_i' => 2, 'note' => 'naik']));
        $risk->refresh();
        $this->assertSame(2, $risk->version);
        $this->assertSame(16, $risk->residual_score);
        $this->assertSame(9, $risk->previous_score);
        $this->assertSame('up', $risk->trend);
        $this->assertSame('pending', $risk->status);
        $this->assertDatabaseHas('approvals', ['subject_id' => $risk->id, 'type' => 'score_change', 'status' => 'pending']);
        $this->assertDatabaseHas('alerts', ['type' => 'score_up']);
        // terkunci saat pending
        $this->as('risk_officer')->put("/risks/{$risk->id}", $this->riskPayload())->assertSessionHas('error');
    }

    public function test_submit_close_and_lesson(): void
    {
        $risk = $this->makeRisk(['status' => 'draft']);
        $this->as('risk_officer')->post("/risks/{$risk->id}/submit", ['note' => 'mohon'])->assertSessionHas('success');
        $this->assertSame('pending', $risk->fresh()->status);
        $this->as('risk_officer')->post("/risks/{$risk->id}/submit")->assertForbidden();
        $mon = $this->makeRisk(['status' => 'monitoring']);
        $this->as('risk_owner')->post("/risks/{$mon->id}/lessons", ['text' => 'pelajaran'])->assertSessionHas('success');
        $this->assertDatabaseHas('lessons', ['subject_id' => $mon->id, 'text' => 'pelajaran']);
        $this->as('risk_owner')->post("/risks/{$mon->id}/close", ['reason' => 'selesai'])->assertSessionHas('success');
        $this->assertDatabaseHas('approvals', ['subject_id' => $mon->id, 'type' => 'closure']);
    }

    public function test_matrix_evaluation_residual_pages(): void
    {
        $this->makeRisk();
        $this->as('management')->get('/risks/matrix')->assertOk()->assertInertia(fn ($p) => $p->component('Risks/Matrix')->has('risks', 1));
        $this->as('management')->get('/risks/evaluation')->assertOk();
        $this->as('management')->get('/risks/residual')->assertOk();
        $this->as('management')->get('/dashboard')->assertOk();
        $this->as('management')->get('/dashboard/executive')->assertOk();
        $this->as('risk_officer')->get('/dashboard/executive')->assertForbidden();
    }
}

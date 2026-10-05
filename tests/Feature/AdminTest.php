<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AdminTest extends TestCase
{
    public function test_only_super_admin_manages_users(): void
    {
        foreach (['risk_admin', 'risk_manager', 'risk_officer', 'auditor'] as $r) {
            $this->as($r)->get('/admin/users')->assertForbidden();
            $this->as($r)->post('/admin/users', ['name' => 'X', 'email' => 'x@uji.test', 'role' => 'auditor'])->assertForbidden();
        }
        $this->as('super_admin')->get('/admin/users')->assertOk();
    }

    public function test_user_lifecycle(): void
    {
        $this->as('super_admin')->post('/admin/users', ['name' => 'Baru', 'email' => 'baru@uji.test', 'role' => 'risk_officer', 'unit_id' => $this->unitB->id, 'scope_units' => [$this->unitA->id]])->assertSessionHas('temp_password');
        $u = User::withoutGlobalScopes()->where('email', 'baru@uji.test')->first();
        $this->assertTrue($u->must_change_password);
        $this->assertSame([$this->unitA->id], $u->scope_units);
        $this->assertEqualsCanonicalizing([$this->unitA->id, $this->unitB->id], $u->accessibleUnitIds());
        $this->as('super_admin')->post('/admin/users', ['name' => 'Dup', 'email' => 'baru@uji.test', 'role' => 'auditor'])->assertSessionHasErrors('email');
        $this->as('super_admin')->post('/admin/users', ['name' => 'Lemah', 'email' => 'lemah@uji.test', 'role' => 'auditor', 'password' => 'lemah'])->assertSessionHasErrors('password');
        $this->as('super_admin')->put("/admin/users/{$u->id}", ['name' => 'Baru 2', 'email' => 'baru@uji.test', 'role' => 'risk_manager', 'active' => true])->assertSessionHas('success');
        $this->assertSame('risk_manager', $u->fresh()->role);
        $this->as('super_admin')->post("/admin/users/{$u->id}/reset-password")->assertSessionHas('temp_password');
        $this->assertDatabaseHas('auth_logs', ['event' => 'password_reset', 'email' => 'baru@uji.test']);
        $this->as('super_admin')->post("/admin/users/{$u->id}/toggle")->assertSessionHas('success');
        $this->assertFalse($u->fresh()->active);
        $me = $this->users['super_admin'];
        $this->as('super_admin')->post("/admin/users/{$me->id}/toggle")->assertSessionHas('error');
        $this->as('super_admin')->delete("/admin/users/{$me->id}")->assertSessionHas('error');
        $this->as('super_admin')->put("/admin/users/{$me->id}", ['name' => $me->name, 'email' => $me->email, 'role' => 'auditor'])->assertSessionHas('error');
        $this->as('super_admin')->delete("/admin/users/{$u->id}")->assertSessionHas('success');
        $this->assertSoftDeleted('users', ['id' => $u->id]);
    }

    public function test_audit_trail_access_and_settings(): void
    {
        $this->makeRisk();
        $this->as('auditor')->get('/admin/audit')->assertOk()->assertInertia(fn ($p) => $p->where('logs.total', fn ($t) => $t >= 1));
        $this->as('risk_officer')->get('/admin/audit')->assertForbidden();
        $this->as('management')->get('/admin/audit')->assertForbidden();
        $this->as('risk_officer')->get('/settings/organization')->assertForbidden();
        $this->as('risk_admin')->get('/settings/organization')->assertOk();
        $this->as('risk_admin')->put('/settings/organization', ['name' => 'X', 'code' => 'X'])->assertForbidden();
        $this->as('super_admin')->put('/settings/organization', ['name' => 'Org Baru', 'code' => 'ORB', 'settings' => ['review_cycle' => 'monthly']])->assertSessionHas('success');
        $this->assertSame('Org Baru', $this->org->fresh()->name);
        $this->as('auditor')->put('/profile', ['name' => 'Auditor Baru', 'preferences' => ['notify' => ['database']]])->assertSessionHas('success');
        $this->assertSame(['database'], $this->users['auditor']->fresh()->preferences['notify']);
    }

    public function test_tenant_isolation(): void
    {
        $other = \App\Models\Organization::create(['name' => 'Lain', 'code' => 'LAIN']);
        \Database\Seeders\CoreSeeder::seedOrganization($other);
        $unit = \App\Models\OrgUnit::withoutGlobalScopes()->create(['organization_id' => $other->id, 'name' => 'U', 'code' => 'U']);
        $cat = \App\Models\RiskCategory::withoutGlobalScopes()->where('organization_id', $other->id)->first();
        $owner = User::withoutGlobalScopes()->create(['organization_id' => $other->id, 'name' => 'O', 'email' => 'o@lain.test', 'password' => 'Secret#Pass123', 'role' => 'risk_manager']);
        $risk = \App\Models\Risk::withoutGlobalScopes()->create(['organization_id' => $other->id, 'code' => 'R-1', 'name' => 'Rahasia', 'unit_id' => $unit->id, 'category_id' => $cat->id, 'owner_id' => $owner->id, 'cause' => 'a', 'event' => 'b', 'impact' => 'c']);
        $this->as('super_admin')->get("/risks/{$risk->id}")->assertNotFound();
        $this->as('super_admin')->get('/risks')->assertInertia(fn ($p) => $p->where('risks.total', 0));
        $this->as('super_admin')->get('/admin/users')->assertInertia(fn ($p) => $p->where('users.total', 7));
        $this->as('risk_officer')->post('/risks', $this->riskPayload(['category_id' => $cat->id]))->assertSessionHasErrors('category_id');
    }
}

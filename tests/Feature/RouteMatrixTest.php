<?php

namespace Tests\Feature;

use App\Models\ActionPlan;
use App\Models\Alert;
use App\Models\Consultation;
use App\Models\ContextFactor;
use App\Models\Control;
use App\Models\CriteriaVersion;
use App\Models\Document;
use App\Models\FrameworkItem;
use App\Models\Improvement;
use App\Models\Incident;
use App\Models\Kri;
use App\Models\Lesson;
use App\Models\LossEvent;
use App\Models\Objective;
use App\Models\Process;
use App\Models\Program;
use App\Models\ReportSchedule;
use App\Models\Review;
use App\Models\RiskCategory;
use App\Models\Scope;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Matriks rute × peran (spesifikasi §16.2 "uji otomatis untuk setiap kombinasi peran × aksi"):
 * 1) Setiap halaman GET dapat dibuka setiap peran tanpa galat server (200/302/403/404 saja).
 * 2) Peran baca-saja (Auditor, Management) ditolak pada semua rute yang mengubah data,
 *    kecuali rute akun pribadi dan keputusan persetujuan (Management) yang memang diizinkan.
 */
class RouteMatrixTest extends TestCase
{
    private array $params = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->actingAs($this->users['risk_manager']);
        $risk = $this->makeRisk(['status' => 'draft']);
        $obj = Objective::create(['code' => 'SS-1', 'name' => 'Sasaran']);
        $prog = Program::create(['name' => 'Program', 'objective_id' => $obj->id]);
        $proc = Process::create(['name' => 'Proses', 'program_id' => $prog->id]);
        $scope = Scope::create(['name' => 'Lingkup']);
        $factor = ContextFactor::create(['scope_id' => $scope->id, 'kind' => 'internal', 'factor' => 'SDM', 'condition' => 'x', 'nature' => 'weakness']);
        $cons = Consultation::create(['title' => 'Rapat', 'held_on' => now(), 'status' => 'done']);
        $control = Control::create(['code' => 'C-1', 'name' => 'K', 'frequency' => 'monthly', 'type' => 'preventive', 'mode' => 'manual']);
        $plan = ActionPlan::create(['code' => 'AP-1', 'risk_id' => $risk->id, 'title' => 'P', 'priority' => 'low', 'due_date' => now()->addMonth(), 'unit_id' => $this->unitA->id]);
        $kri = Kri::create(['code' => 'KRI-1', 'name' => 'K', 'risk_id' => $risk->id, 'source' => 'manual', 'frequency' => 'monthly', 'direction' => 'up_bad', 'threshold_warn' => 1, 'threshold_crit' => 2]);
        $inc = Incident::create(['code' => 'INC-1', 'title' => 'I', 'occurred_at' => now(), 'status' => 'reported', 'unit_id' => $this->unitA->id]);
        $loss = LossEvent::create(['year' => 2025, 'risk_name' => 'r', 'event' => 'e', 'amount' => 1]);
        $review = Review::create(['risk_id' => $risk->id, 'period_type' => 'adhoc', 'period' => 'x', 'current_score' => 9, 'trend' => 'flat', 'decision' => 'continue']);
        Storage::disk('local')->put('documents/x.pdf', '%PDF-1.4');
        $doc = Document::create(['type' => 'sop', 'title' => 'D', 'path' => 'documents/x.pdf', 'original_name' => 'x.pdf', 'mime' => 'application/pdf', 'size' => 8, 'hash' => 'h', 'subject_type' => 'risk', 'subject_id' => $risk->id, 'uploaded_by' => $this->users['risk_manager']->id]);
        $imp = Improvement::create(['code' => 'IMP-1', 'source_type' => 'audit', 'title' => 'I', 'status' => 'open']);
        $lesson = Lesson::create(['text' => 'L']);
        $alert = Alert::create(['type' => 't', 'severity' => 'info', 'title' => 'A', 'subject_type' => 'risk', 'subject_id' => $risk->id]);
        $sched = ReportSchedule::create(['user_id' => $this->users['risk_manager']->id, 'type' => 'kri', 'format' => 'pdf', 'frequency' => 'monthly', 'day' => 1, 'recipients' => ['a@b.c']]);
        $approval = app(ApprovalService::class)->submit($this->makeRisk(['status' => 'draft', 'residual_l' => 2, 'residual_i' => 2]), 'new_risk', $this->users['risk_officer']);
        $victim = User::create(['name' => 'Korban', 'email' => 'korban@uji.test', 'password' => 'Secret#Pass123', 'role' => 'risk_officer']);
        auth()->logout();
        $this->params = ['risk' => $risk->id, 'unit' => $this->unitB->id, 'objective' => $obj->id, 'program' => $prog->id, 'process' => $proc->id, 'scope' => $scope->id, 'factor' => $factor->id,
            'consultation' => $cons->id, 'criteria' => CriteriaVersion::withoutGlobalScopes()->first()->id, 'category' => RiskCategory::withoutGlobalScopes()->first()->id, 'approval' => $approval->id,
            'control' => $control->id, 'plan' => $plan->id, 'kri' => $kri->id, 'incident' => $inc->id, 'loss' => $loss->id, 'review' => $review->id, 'document' => $doc->id,
            'improvement' => $imp->id, 'lesson' => $lesson->id, 'item' => FrameworkItem::withoutGlobalScopes()->first()->id, 'alert' => $alert->id, 'user' => $victim->id, 'schedule' => $sched->id, 'type' => 'risk'];
    }

    private function routes(array $methods): array
    {
        $out = [];
        foreach (Route::getRoutes() as $r) {
            $m = array_values(array_diff($r->methods(), ['HEAD']))[0];
            if (!in_array($m, $methods, true) || !str_contains(implode(',', $r->gatherMiddleware()), 'auth') || str_starts_with($r->uri(), 'up')) {
                continue;
            }
            $uri = preg_replace_callback('/\{(\w+)\??\}/', fn ($x) => (string) ($this->params[$x[1]] ?? 1), $r->uri());
            $out[] = [$m, '/' . ltrim($uri, '/'), (string) $r->getName()];
        }
        return $out;
    }

    public function test_every_get_route_renders_for_every_role_without_server_error(): void
    {
        $failures = [];
        foreach (array_keys(User::ROLES) as $role) {
            foreach ($this->routes(['GET']) as [$m, $uri, $name]) {
                if (in_array($name, ['reviews.minutes', 'risks.similar'], true)) {
                    $uri .= $name === 'reviews.minutes' ? '?period=x' : '?unit_id=' . $this->unitA->id . '&text=abc';
                }
                if ($name === 'risks.export') {
                    $uri .= '?format=xlsx';
                }
                $status = $this->actingAs($this->users[$role])->withSession(['mr_login_at' => time(), 'mr_last_activity' => time()])->get($uri)->getStatusCode();
                if ($status >= 500) {
                    $failures[] = "{$role} GET {$uri} → {$status}";
                }
            }
        }
        $this->assertSame([], $failures);
    }

    public function test_read_only_roles_cannot_mutate_anything(): void
    {
        $allowed = [
            'auditor' => ['logout', 'logout.others', 'password.update', 'profile.update', 'alerts.read', 'alerts.read-all', 'reports.generate'],
            'management' => ['logout', 'logout.others', 'password.update', 'profile.update', 'alerts.read', 'alerts.read-all', 'reports.generate', 'reports.schedules.store', 'approvals.decide', 'ai.run'],
        ];
        $failures = [];
        foreach ($allowed as $role => $ok) {
            foreach ($this->routes(['POST', 'PUT', 'DELETE']) as [$m, $uri, $name]) {
                if (in_array($name, $ok, true)) {
                    continue;
                }
                $res = $this->actingAs($this->users[$role])->withSession(['mr_login_at' => time(), 'mr_last_activity' => time()])->call($m, $uri, []);
                if (!in_array($res->getStatusCode(), [403, 404], true)) {
                    $failures[] = "{$role} {$m} {$uri} ({$name}) → {$res->getStatusCode()}";
                }
            }
        }
        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_unit_scoped_officer_cannot_touch_other_unit_objects(): void
    {
        $this->actingAs($this->users['risk_manager']);
        $riskB = $this->makeRisk(['status' => 'draft'], $this->unitB);
        $planB = ActionPlan::create(['code' => 'AP-B', 'risk_id' => $riskB->id, 'title' => 'B', 'priority' => 'low', 'due_date' => now()->addMonth(), 'unit_id' => $this->unitB->id]);
        $incB = Incident::create(['code' => 'INC-B', 'title' => 'B', 'occurred_at' => now(), 'status' => 'reported', 'unit_id' => $this->unitB->id]);
        $ctlB = Control::create(['code' => 'C-B', 'name' => 'B', 'frequency' => 'monthly', 'type' => 'preventive', 'mode' => 'manual', 'unit_id' => $this->unitB->id]);
        auth()->logout();
        $o = fn () => $this->actingAs($this->users['risk_officer'])->withSession(['mr_login_at' => time(), 'mr_last_activity' => time()]);
        $o()->get("/risks/{$riskB->id}")->assertForbidden();
        $o()->get("/risks/{$riskB->id}/edit")->assertForbidden();
        $o()->put("/risks/{$riskB->id}", $this->riskPayload(['unit_id' => $this->unitB->id]))->assertForbidden();
        $o()->post("/risks/{$riskB->id}/submit")->assertForbidden();
        $o()->get("/action-plans/{$planB->id}")->assertForbidden();
        $o()->post("/action-plans/{$planB->id}/progress", ['progress' => 10])->assertForbidden();
        $o()->get("/incidents/{$incB->id}")->assertForbidden();
        $o()->put("/incidents/{$incB->id}", ['title' => 'x', 'occurred_at' => now()->format('Y-m-d H:i'), 'status' => 'reported'])->assertForbidden();
        $o()->get("/controls/{$ctlB->id}")->assertForbidden();
        $o()->post('/action-plans', ['risk_id' => $riskB->id, 'title' => 'x', 'priority' => 'low', 'due_date' => now()->addDay()->toDateString()])->assertForbidden();
        $o()->post('/reviews', ['risk_id' => $riskB->id, 'period_type' => 'adhoc', 'period' => 'x', 'decision' => 'continue'])->assertForbidden();
        $o()->postJson('/ai', ['feature' => 'explain_score', 'risk_id' => $riskB->id])->assertForbidden();
    }

    public function test_admin_only_routes_are_denied_to_operational_roles(): void
    {
        $prefixes = ['users.', 'units.store', 'units.update', 'units.destroy', 'criteria.store', 'criteria.activate', 'categories.', 'settings.organization.update', 'framework.update',
            'objectives.store', 'objectives.update', 'objectives.destroy', 'programs.', 'processes.', 'scopes.', 'factors.', 'consultations.', 'reviews.snapshot', 'reviews.destroy', 'losses.destroy',
            'risks.destroy', 'controls.destroy', 'action-plans.destroy', 'kris.destroy', 'incidents.destroy', 'improvements.destroy', 'lessons.destroy'];
        $failures = [];
        foreach (['risk_officer', 'risk_owner'] as $role) {
            foreach ($this->routes(['GET', 'POST', 'PUT', 'DELETE']) as [$m, $uri, $name]) {
                $hit = false;
                foreach ($prefixes as $p) {
                    $hit = $hit || ($name === $p || (str_ends_with($p, '.') && str_starts_with($name, $p) && $m !== 'GET'));
                }
                if (!$hit || ($m === 'GET' && $name !== 'users.index')) {
                    continue;
                }
                $res = $this->actingAs($this->users[$role])->withSession(['mr_login_at' => time(), 'mr_last_activity' => time()])->call($m, $uri, []);
                if (!in_array($res->getStatusCode(), [403, 404], true)) {
                    $failures[] = "{$role} {$m} {$uri} ({$name}) → {$res->getStatusCode()}";
                }
            }
        }
        $this->assertSame([], $failures, implode("\n", $failures));
    }
}

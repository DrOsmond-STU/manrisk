<?php

namespace Tests\Feature;

use App\Models\ActionPlan;
use App\Models\AiDashboard;
use App\Models\AiInteraction;
use App\Models\Control;
use App\Models\Incident;
use App\Models\Kri;
use App\Models\Organization;
use App\Models\User;
use App\Services\Ai\AssistantService;
use Tests\TestCase;

class AiAssistantTest extends TestCase
{
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->users['risk_manager']);
        $a = $this->makeRisk(['name' => 'Gangguan layanan pusat data', 'residual_l' => 4, 'residual_i' => 4, 'residual_exposure' => 500000000]);
        $b = $this->makeRisk(['name' => 'Risiko unit B rahasia', 'residual_l' => 5, 'residual_i' => 5], $this->unitB);
        $plan = ActionPlan::create(['code' => 'AP-1', 'risk_id' => $a->id, 'title' => 'Server cadangan', 'priority' => 'high', 'due_date' => now()->subDays(5), 'progress' => 40, 'budget' => 1000000, 'unit_id' => $this->unitA->id]);
        ActionPlan::create(['code' => 'AP-B', 'risk_id' => $b->id, 'title' => 'Rencana unit B', 'priority' => 'low', 'due_date' => now()->addMonth(), 'unit_id' => $this->unitB->id]);
        $ctl = Control::create(['code' => 'C-1', 'name' => 'Backup harian', 'frequency' => 'daily', 'type' => 'preventive', 'mode' => 'automated', 'design_eff' => 2, 'operating_eff' => 2, 'unit_id' => $this->unitA->id]);
        $ctl->risks()->sync([$a->id]);
        Kri::create(['code' => 'KRI-1', 'name' => 'Downtime', 'risk_id' => $a->id, 'source' => 'manual', 'frequency' => 'monthly', 'direction' => 'up_bad', 'threshold_warn' => 1, 'threshold_crit' => 2, 'last_value' => 3, 'status' => 'critical']);
        $inc = Incident::create(['code' => 'INC-1', 'title' => 'Server mati', 'occurred_at' => now()->subDays(3), 'status' => 'reported', 'unit_id' => $this->unitA->id, 'risk_id' => $a->id, 'loss_amount' => 2500000, 'cause' => 'listrik padam']);
        Incident::create(['code' => 'INC-B', 'title' => 'Insiden unit B', 'occurred_at' => now()->subDays(2), 'status' => 'reported', 'unit_id' => $this->unitB->id, 'loss_amount' => 9000000]);
        $this->ids = ['riskA' => $a->id, 'riskB' => $b->id, 'plan' => $plan->id, 'control' => $ctl->id, 'incident' => $inc->id, 'codeB' => $b->code];
        auth()->logout();
    }

    public function test_assistant_answers_quick_actions_on_every_page(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        config(['manrisk.ai.daily_limit' => 500]);
        $detail = ['risk' => $this->ids['riskA'], 'action_plan' => $this->ids['plan'], 'control' => $this->ids['control'], 'incident' => $this->ids['incident']];
        foreach (AssistantService::MODULES as $module => [$label, $actions]) {
            if ($module === 'rmi_assessment') {
                continue;
            }
            $this->as('super_admin')->getJson('/ai/assist?module=' . $module)->assertOk()->assertJsonPath('module', $module);
            foreach (array_keys($actions) as $action) {
                $res = $this->as('super_admin')->postJson('/ai/assist', ['module' => $module, 'action' => $action, 'id' => $detail[$module] ?? null]);
                $res->assertOk();
                $this->assertNotEmpty($res->json('text'), "$module/$action");
                $this->assertSame('local-heuristic', $res->json('model'));
            }
        }
        $this->assertGreaterThan(50, AiInteraction::withoutGlobalScopes()->where('feature', 'like', 'assist:%')->count());
    }

    public function test_assistant_detail_page_content_and_free_question_with_history(): void
    {
        $res = $this->as('risk_officer')->postJson('/ai/assist', ['module' => 'risk', 'id' => $this->ids['riskA'], 'action' => 'controls'])->assertOk();
        $this->assertStringContainsString('Preventif', $res->json('text'));
        $res = $this->as('risk_officer')->postJson('/ai/assist', ['module' => 'action_plans', 'question' => 'Mana yang paling mendesak?',
            'history' => [['role' => 'user', 'content' => 'Halo'], ['role' => 'assistant', 'content' => 'Halo, ada yang bisa dibantu?']]])->assertOk();
        $this->assertStringContainsString('AP-1', $res->json('text'));
        $this->assertSame("/action-plans/{$this->ids['plan']}", $res->json('refs.AP-1'));
    }

    public function test_assistant_respects_unit_scope_roles_and_validation(): void
    {
        // objek unit lain ditolak; ringkasan tidak memuat data unit lain
        $this->as('risk_officer')->postJson('/ai/assist', ['module' => 'risk', 'id' => $this->ids['riskB'], 'action' => 'explain'])->assertForbidden();
        $res = $this->as('risk_officer')->postJson('/ai/assist', ['module' => 'risks', 'action' => 'priority'])->assertOk();
        $this->assertStringNotContainsString($this->ids['codeB'], $res->json('text'));
        $this->assertArrayNotHasKey($this->ids['codeB'], $res->json('refs'));
        $this->assertStringNotContainsString('unit B rahasia', $res->json('text'));
        $res = $this->as('risk_officer')->postJson('/ai/assist', ['module' => 'incidents', 'action' => 'loss'])->assertOk();
        $this->assertStringNotContainsString('INC-B', $res->json('text'));
        // peran & halaman terbatas
        $this->as('auditor')->getJson('/ai/assist?module=risks')->assertForbidden();
        $this->as('auditor')->postJson('/ai/assist', ['module' => 'risks', 'action' => 'summary'])->assertForbidden();
        $this->as('risk_officer')->postJson('/ai/assist', ['module' => 'users', 'action' => 'summary'])->assertForbidden();
        $this->as('risk_officer')->postJson('/ai/assist', ['module' => 'audit', 'action' => 'summary'])->assertForbidden();
        $this->as('board')->postJson('/ai/assist', ['module' => 'executive', 'action' => 'summary'])->assertOk();
        $this->as('risk_officer')->postJson('/ai/assist', ['module' => 'risks', 'action' => 'hapus_semua'])->assertStatus(422);
        $this->as('risk_officer')->postJson('/ai/assist', ['module' => 'risk', 'action' => 'explain'])->assertStatus(422);
        $this->as('risk_officer')->postJson('/ai/assist', ['module' => 'risks'])->assertStatus(422);
        $this->as('risk_officer')->postJson('/ai/assist', ['module' => 'risk', 'id' => 999999, 'action' => 'explain'])->assertNotFound();
    }

    public function test_assistant_quota_is_enforced(): void
    {
        config(['manrisk.ai.daily_limit' => 1]);
        $this->as('risk_officer')->postJson('/ai/assist', ['module' => 'risks', 'action' => 'summary'])->assertOk()->assertJsonPath('remaining', 0);
        $this->as('risk_officer')->postJson('/ai/assist', ['module' => 'risks', 'action' => 'summary'])->assertStatus(422)->assertJsonValidationErrors('ai');
        config(['manrisk.ai.enabled' => false]);
        $this->as('risk_manager')->postJson('/ai/analytics/plan', ['prompt' => 'jumlah risiko per unit'])->assertStatus(422);
    }

    public function test_analytics_plans_dashboard_from_natural_language(): void
    {
        $this->as('risk_manager')->get('/ai/analytics')->assertOk()->assertInertia(fn ($p) => $p->component('Ai/Analytics')->has('examples')->has('dashboards')->has('datasets'));
        $res = $this->as('risk_manager')->postJson('/ai/analytics/plan', ['prompt' => 'Jumlah risiko per unit kerja dan tren insiden per bulan 12 bulan terakhir'])->assertOk();
        $w = $res->json('widgets');
        $this->assertCount(2, $w);
        $this->assertSame('risks', $w[0]['spec']['dataset']);
        $this->assertSame('unit', $w[0]['spec']['group']);
        $this->assertEqualsCanonicalizing(['Unit A', 'Unit B'], $w[0]['labels']);
        $this->assertSame('incidents', $w[1]['spec']['dataset']);
        $this->assertSame('line', $w[1]['type']);
        $this->assertCount(12, $w[1]['labels']);
        $this->assertEquals(2, array_sum($w[1]['series'][0]['data']));
        $this->assertNotEmpty($res->json('summary'));

        $res = $this->as('risk_manager')->postJson('/ai/analytics/plan', ['prompt' => '10 risiko dengan skor residual tertinggi'])->assertOk();
        $this->assertSame('table', $res->json('widgets.0.type'));
        $this->assertTrue($res->json('widgets.0.spec.list'));
        $this->assertSame($this->ids['codeB'], $res->json('widgets.0.rows.0.0'));
        $this->assertSame("/risks/{$this->ids['riskB']}", $res->json('widgets.0.links.0'));

        $res = $this->as('risk_manager')->postJson('/ai/analytics/plan', ['prompt' => 'action plan terlambat'])->assertOk();
        $this->assertSame('kpi', $res->json('widgets.0.type'));
        $this->assertSame(1.0, (float) $res->json('widgets.0.value'));

        $res = $this->as('risk_manager')->postJson('/ai/analytics/plan', ['prompt' => 'total eksposur residual per kategori'])->assertOk();
        $this->assertSame('sum:residual_exposure', $res->json('widgets.0.spec.metric'));
        $this->assertSame(500000000.0, (float) array_sum($res->json('widgets.0.series.0.data')));

        $res = $this->as('risk_manager')->postJson('/ai/analytics/plan', ['prompt' => 'dashboard risiko eksekutif'])->assertOk();
        $this->assertGreaterThanOrEqual(6, count($res->json('widgets')));
        $this->assertSame(1, AiInteraction::withoutGlobalScopes()->where('feature', 'analytics')->where('user_id', $this->users['risk_manager']->id)->count() >= 1 ? 1 : 0);
    }

    public function test_analytics_data_is_scoped_and_spec_is_whitelisted(): void
    {
        $widgets = [
            ['type' => 'kpi', 'dataset' => 'risks', 'metric' => 'count'],
            ['type' => 'kpi', 'dataset' => 'incidents', 'metric' => 'sum:loss_amount'],
            ['type' => 'bar', 'dataset' => 'users', 'metric' => 'count', 'group' => 'role'],          // dataset tidak dikenal → dibuang
            ['type' => 'bar', 'dataset' => 'risks', 'metric' => 'sum:password', 'group' => 'DROP TABLE'], // kunci asing → dinormalisasi
            ['type' => 'table', 'dataset' => 'risks', 'list' => true, 'metric' => 'max:residual_score'],
        ];
        $res = $this->as('risk_officer')->postJson('/ai/analytics/run', ['widgets' => $widgets])->assertOk();
        $w = $res->json('widgets');
        $this->assertCount(4, $w);
        $this->assertSame(1.0, (float) $w[0]['value']);            // hanya risiko unit A
        $this->assertSame(2500000.0, (float) $w[1]['value']);      // insiden unit B tidak dihitung
        $this->assertSame('count', $w[2]['spec']['metric']);
        $this->assertSame('kpi', $w[2]['type']);
        $this->assertNotContains($this->ids['codeB'], array_column($w[3]['rows'], 0));
        $this->as('risk_manager')->postJson('/ai/analytics/run', ['widgets' => [$widgets[0]]])->assertOk()->assertJsonPath('widgets.0.value', 2);
        $this->as('auditor')->postJson('/ai/analytics/run', ['widgets' => [$widgets[0]]])->assertForbidden();
        $this->as('auditor')->get('/ai/analytics')->assertForbidden();
        $this->as('risk_manager')->postJson('/ai/analytics/run', ['widgets' => []])->assertStatus(422);
    }

    public function test_saved_dashboards_ownership_sharing_and_tenant_isolation(): void
    {
        $payload = ['title' => 'Dashboard saya', 'prompt' => 'risiko per unit', 'widgets' => [['type' => 'bar', 'dataset' => 'risks', 'metric' => 'count', 'group' => 'unit'], ['dataset' => 'nope']]];
        $id = $this->as('risk_officer')->postJson('/ai/dashboards', $payload)->assertOk()->json('id');
        $d = AiDashboard::withoutGlobalScopes()->find($id);
        $this->assertCount(1, $d->widgets);
        $this->assertFalse($d->shared);
        // pribadi: tidak terlihat pengguna lain, tidak bisa diubah/hapus orang lain
        $this->as('risk_manager')->get('/ai/analytics')->assertInertia(fn ($p) => $p->has('dashboards', 0));
        $this->as('risk_manager')->putJson("/ai/dashboards/{$id}", $payload)->assertForbidden();
        $this->as('risk_manager')->deleteJson("/ai/dashboards/{$id}")->assertForbidden();
        // dibagikan: terlihat, data tetap dihitung menurut hak akses penampil
        $this->as('risk_officer')->putJson("/ai/dashboards/{$id}", $payload + ['shared' => true])->assertOk();
        $this->as('risk_manager')->get('/ai/analytics')->assertInertia(fn ($p) => $p->has('dashboards', 1)->where('dashboards.0.mine', false));
        // organisasi lain tidak dapat menjangkau
        auth()->logout(); // hook BelongsToOrganization memaksa organisasi pengguna yang sedang masuk
        $other = Organization::create(['name' => 'Org Lain', 'code' => 'LAIN']);
        $stranger = User::withoutGlobalScopes()->create(['organization_id' => $other->id, 'name' => 'Asing', 'email' => 'asing@lain.test', 'password' => 'Secret#Pass123', 'role' => 'super_admin', 'active' => true, 'password_changed_at' => now()]);
        $this->actingAs($stranger)->deleteJson("/ai/dashboards/{$id}")->assertNotFound();
        $this->actingAs($stranger)->get('/ai/analytics')->assertInertia(fn ($p) => $p->has('dashboards', 0));
        $this->as('risk_officer')->deleteJson("/ai/dashboards/{$id}")->assertOk();
        $this->assertNull(AiDashboard::withoutGlobalScopes()->find($id));
    }

    public function test_provider_receives_scoped_context_and_falls_back_on_failure(): void
    {
        config(['manrisk.ai.api_key' => 'sk-test']);
        $reply = ['model' => 'model-uji', 'content' => [['type' => 'text', 'text' => 'Prioritaskan R-2026-001.']], 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]];
        $status = 200;
        \Illuminate\Support\Facades\Http::fake(function () use (&$reply, &$status) {
            return \Illuminate\Support\Facades\Http::response($reply, $status);
        });
        $res = $this->as('risk_officer')->postJson('/ai/assist', ['module' => 'risks', 'question' => 'Apa prioritas saya?', 'history' => [['role' => 'assistant', 'content' => 'awal'], ['role' => 'user', 'content' => 'Halo'], ['role' => 'assistant', 'content' => 'Hai']]])->assertOk();
        $res->assertJsonPath('model', 'model-uji')->assertJsonPath('text', 'Prioritaskan R-2026-001.');
        \Illuminate\Support\Facades\Http::assertSent(function ($req) {
            $b = $req->data();
            return str_contains($b['system'], 'Data halaman') && !str_contains($b['system'], $this->ids['codeB']) && !str_contains($b['system'], 'unit B rahasia')
                && $b['messages'][0]['role'] === 'user' && end($b['messages'])['content'] === 'Apa prioritas saya?' && count($b['messages']) === 3;
        });

        // AI Analitik: model hanya menerima skema (tanpa data) dan widget tidak valid dibuang
        $reply = (['model' => 'model-uji', 'content' => [['type' => 'text', 'text' => '```json {"title":"Dari AI","widgets":[{"type":"pie","dataset":"risks","metric":"count","group":"level"},{"type":"bar","dataset":"secrets","metric":"count"}]} ```']]]);
        $res = $this->as('risk_manager')->postJson('/ai/analytics/plan', ['prompt' => 'komposisi level'])->assertOk();
        $res->assertJsonPath('title', 'Dari AI')->assertJsonCount(1, 'widgets')->assertJsonPath('widgets.0.type', 'pie');
        \Illuminate\Support\Facades\Http::assertSent(fn ($req) => str_contains($req->data()['system'] ?? '', 'residual_exposure') && !str_contains(json_encode($req->data()), 'Gangguan layanan pusat data'));

        // penyedia gagal → jawaban lokal tetap tersedia dan dicatat tidak berhasil
        $reply = 'down';
        $status = 500;
        $this->as('risk_officer')->postJson('/ai/assist', ['module' => 'kris', 'action' => 'breach'])->assertOk()->assertJsonPath('model', 'fallback');
        $this->as('risk_manager')->postJson('/ai/analytics/plan', ['prompt' => 'jumlah risiko per unit'])->assertOk()->assertJsonPath('widgets.0.spec.group', 'unit');
        $this->assertTrue(AiInteraction::withoutGlobalScopes()->where('ok', false)->exists());
    }
}

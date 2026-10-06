<?php

namespace App\Http\Controllers;

use App\Models\ActionPlan;
use App\Models\Alert;
use App\Models\Approval;
use App\Models\Control;
use App\Models\Incident;
use App\Models\Kri;
use App\Models\OrgUnit;
use App\Models\Risk;
use App\Models\RiskCategory;
use App\Models\RiskSnapshot;
use App\Support\Scoring;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $f = $request->validate(['unit_id' => ['nullable', 'integer']]);
        $unitFilter = null;
        if (!empty($f['unit_id']) && ($unit = OrgUnit::find($f['unit_id'])) && $user->canAccessUnit($unit->id)) {
            $unitFilter = $unit->descendantIds();
        }
        $risks = $this->scopeUnits(Risk::query())->when($unitFilter, fn ($q) => $q->whereIn('unit_id', $unitFilter))->where('status', '!=', 'closed')
            ->with(['unit:id,name', 'category:id,name', 'owner:id,name', 'objective:id,code,name', 'process:id,name', 'actionPlans:id,risk_id,code,title,progress,expected_dl,expected_di,cancelled_at,completed_at,due_date'])->get();
        $byLevel = ['low' => 0, 'medium' => 0, 'high' => 0, 'very_high' => 0];
        $heat = [];
        $heatInh = [];
        foreach ($risks as $r) {
            $byLevel[$r->residual_level] = ($byLevel[$r->residual_level] ?? 0) + 1;
            $heat["{$r->residual_l}-{$r->residual_i}"] = ($heat["{$r->residual_l}-{$r->residual_i}"] ?? 0) + 1;
            $heatInh["{$r->inherent_l}-{$r->inherent_i}"] = ($heatInh["{$r->inherent_l}-{$r->inherent_i}"] ?? 0) + 1;
        }
        $plans = $risks->flatMap->actionPlans->whereNull('cancelled_at');
        $overdue = $plans->filter(fn ($p) => $p->progress < 100 && $p->due_date && $p->due_date->lt(now()->startOfDay()));
        $periods = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->format('Y-m'));
        $riskIds = $risks->pluck('id');
        $trend = RiskSnapshot::whereIn('period', $periods)->whereIn('risk_id', $riskIds)->selectRaw('period, avg(residual_score) as avg_score, count(*) as n')->groupBy('period')->orderBy('period')->get()->keyBy('period');
        $metrics = $this->metrics($risks);
        $kriBreach = $this->scopedKris()->where('active', true)->whereIn('status', ['warning', 'critical'])->count();
        // id ikut dikirim agar batang grafik dapat dibuka sebagai register tersaring
        $group = fn ($key, $label) => $risks->filter(fn ($r) => $r->{$key})->groupBy($key)->map(fn ($g, $id) => ['id' => $id, 'name' => $label($g->first()), 'n' => $g->count(), 'high' => $g->whereIn('residual_level', ['high', 'very_high'])->count()])->sortByDesc('n')->take(8)->values();
        $top = $risks->sortBy([['residual_score', 'desc'], ['inherent_score', 'desc']])->take(8)->values();
        $summary = ['total' => $risks->count(), 'high' => $byLevel['high'] + $byLevel['very_high'], 'escalate' => $risks->whereIn('evaluation', ['escalate', 'critical'])->count(),
            'realization' => $metrics['realization'], 'overdue' => $overdue->count(), 'kri_breach' => $kriBreach, 'up' => $risks->where('trend', 'up')->count(), 'down' => $risks->where('trend', 'down')->count()];

        return Inertia::render('Dashboard/Index', [
            'filters' => $f,
            'units' => $user->isUnitScoped() ? $this->unitOptions(true) : $this->unitOptions(),
            'kpi' => [
                'total' => $risks->count(),
                'high' => $summary['high'],
                'critical' => $risks->where('evaluation', 'critical')->count(),
                'plans_total' => $plans->count(),
                'plans_overdue' => $overdue->count(),
                'kri_breach' => $kriBreach,
                'incidents_open' => $this->scopeUnits(Incident::query())->when($unitFilter, fn ($q) => $q->whereIn('unit_id', $unitFilter))->where('status', '!=', 'closed')->count(),
                // Sama dengan badge "Persetujuan": pengajuan yang menunggu keputusan pengguna ini
                'pending_approvals' => \App\Support\UnitScope::morph(Approval::query(), $user, false)->where('status', 'pending')->with('steps')->get()
                    ->filter(fn ($a) => $a->requester_id !== $user->id && ($s = $a->steps->firstWhere('step_no', $a->current_step)) && ($s->role === $user->role || $user->role === 'super_admin'))->count(),
                'controls_weak' => $this->scopedControls()->where('active', true)->where(fn ($q) => $q->where('operating_eff', '<=', 2)->orWhere('design_eff', '<=', 2))->count(),
                'no_controls' => $riskIds->isEmpty() ? 0 : Risk::whereIn('id', $riskIds)->doesntHave('controls')->count(),
                'new_year' => $this->scopeUnits(Risk::query())->whereYear('created_at', now()->year)->count(),
                'closed_year' => $this->scopeUnits(Risk::query())->where('status', 'closed')->whereYear('closed_at', now()->year)->count(),
                'treating' => $risks->where('status', 'treating')->count(),
                'above_target' => $risks->filter(fn ($r) => $r->residual_score > $r->target_score)->count(),
                'up' => $summary['up'], 'down' => $summary['down'], 'flat' => $risks->where('trend', 'flat')->count(),
            ],
            'metrics' => $metrics,
            'by_level' => $byLevel,
            'heat' => $heat,
            'heat_inherent' => $heatInh,
            'by_category' => $group('category_id', fn ($r) => $r->category?->name ?? '—'),
            'by_unit' => $group('unit_id', fn ($r) => $r->unit?->name ?? '—'),
            'by_objective' => $group('objective_id', fn ($r) => $r->objective ? "{$r->objective->code} · {$r->objective->name}" : '—'),
            'by_process' => $group('process_id', fn ($r) => $r->process?->name ?? '—'),
            'top_risks' => $top->map(fn ($r) => $r->only('id', 'code', 'name', 'unit_id', 'residual_score', 'inherent_score', 'residual_level', 'evaluation', 'trend', 'status') + ['unit' => $r->unit?->name, 'owner' => $r->owner?->name]),
            'emerging' => $risks->filter(fn ($r) => $r->trend === 'up' || $r->created_at?->gt(now()->subDays(30)))->sortByDesc('residual_score')->take(6)->values()->map(fn ($r) => $r->only('id', 'code', 'name', 'residual_score', 'residual_level', 'trend') + ['new' => $r->created_at?->gt(now()->subDays(30))]),
            'trend' => $periods->map(fn ($p) => ['period' => $p, 'avg' => round((float) ($trend[$p]->avg_score ?? 0), 1), 'n' => (int) ($trend[$p]->n ?? 0)]),
            'alerts' => \App\Support\UnitScope::morph(Alert::query(), $user)->whereNull('handled_at')->latest()->limit(6)->get(['id', 'type', 'severity', 'title', 'link', 'created_at', 'read_at']),
            'upcoming' => $plans->filter(fn ($p) => $p->progress < 100)->sortBy('due_date')->take(6)->values()->map(fn ($p) => $p->only('id', 'code', 'title', 'due_date', 'progress') + ['status' => $p->computedStatus()]),
            'kris' => $this->scopedKris()->where('active', true)->with('risk:id,code')->orderByRaw("case status when 'critical' then 0 when 'warning' then 1 else 2 end")->limit(6)->get(),
            'recent' => \App\Models\AuditLog::with('user:id,name')->where('organization_id', $user->organization_id)->when($user->isUnitScoped(), fn ($q) => $q->where('user_id', $user->id))
                ->whereIn('action', ['created', 'updated', 'submitted', 'approval_approve', 'approval_reject', 'approval_revise'])->latest('id')->limit(8)->get(['id', 'user_id', 'action', 'subject_type', 'subject_id', 'subject_label', 'created_at']),
            'ai_summary' => app(\App\Services\AiService::class)->available() ? app(\App\Services\AiService::class)->dashboardSummary($summary, $top->map(fn ($r) => $r->only('code', 'name', 'residual_score'))->all(), $user->organization_id . ':' . md5(json_encode($user->accessibleUnitIds()) . json_encode($unitFilter))) : null,
        ]);
    }

    /** Metrik mitigasi & perjalanan risiko (F-DSH-05, F-DSH-07). */
    private function metrics($risks): array
    {
        $scoring = app(Scoring::class);
        $active = $risks->flatMap->actionPlans->whereNull('cancelled_at');
        $n = max(1, $risks->count());
        $effective = $risks->filter(fn ($r) => (Scoring::LEVEL_ORDER[Scoring::levelFromScore($r->inherent_score)] ?? 0) - (Scoring::LEVEL_ORDER[$r->residual_level] ?? 0) >= 1)->count();
        $proj = $risks->map(fn ($r) => $scoring->projected($r)['score']);
        return [
            'realization' => (int) round((float) $active->avg('progress')),
            'effectiveness' => (int) round($effective / $n * 100),
            'journey' => ['inherent' => round((float) $risks->avg('inherent_score'), 1), 'residual' => round((float) $risks->avg('residual_score'), 1), 'projected' => round((float) $proj->avg(), 1), 'target' => round((float) $risks->avg('target_score'), 1)],
        ];
    }

    public function executive(Request $request)
    {
        $risks = Risk::where('status', '!=', 'closed')->with(['category:id,name,appetite,tolerance', 'unit:id,name'])->get();
        $scoring = app(Scoring::class);
        $cats = RiskCategory::where('active', true)->orderBy('sort')->get()->map(function ($c) use ($risks) {
            $rs = $risks->where('category_id', $c->id);
            $max = (int) ($rs->max('residual_score') ?? 0);
            return ['id' => $c->id, 'name' => $c->name, 'appetite' => $c->appetite, 'tolerance' => $c->tolerance, 'n' => $rs->count(), 'max' => $max,
                'status' => $max > $c->tolerance ? 'breach' : ($max > $c->appetite ? 'watch' : 'ok')];
        });
        $periods = collect(range(11, 0))->map(fn ($i) => now()->subMonths($i)->format('Y-m'));
        $snap = RiskSnapshot::whereIn('period', $periods)->get()->groupBy('period');
        $trend = $periods->map(fn ($p) => ['period' => $p,
            'high' => $snap->get($p, collect())->whereIn('level', ['high', 'very_high'])->count(),
            'total' => $snap->get($p, collect())->count(),
            'avg' => round((float) $snap->get($p, collect())->avg('residual_score'), 1)]);
        $plans = ActionPlan::whereNull('cancelled_at')->get();
        $quarters = collect(range(3, 0))->map(function ($i) {
            $d = now()->subQuarters($i);
            return ['label' => 'TW' . $d->quarter . ' ' . $d->year, 'period' => $d->copy()->lastOfQuarter()->min(now())->format('Y-m')];
        });
        $qs = RiskSnapshot::whereIn('period', $quarters->pluck('period'))->get()->groupBy('period');
        $heat = ['residual' => [], 'inherent' => []];
        foreach ($risks as $r) {
            $heat['residual']["{$r->residual_l}-{$r->residual_i}"] = ($heat['residual']["{$r->residual_l}-{$r->residual_i}"] ?? 0) + 1;
            $heat['inherent']["{$r->inherent_l}-{$r->inherent_i}"] = ($heat['inherent']["{$r->inherent_l}-{$r->inherent_i}"] ?? 0) + 1;
        }
        $risks->load('actionPlans:id,risk_id,progress,expected_dl,expected_di,cancelled_at');
        $byLv = fn ($score) => Scoring::levelFromScore((int) $score);
        return Inertia::render('Dashboard/Executive', [
            'metrics' => $this->metrics($risks),
            'heat' => $heat,
            'quarters' => $quarters->map(fn ($q) => ['label' => $q['label']] + collect(['very_high', 'high', 'medium', 'low'])->mapWithKeys(fn ($lv) => [$lv => $qs->get($q['period'], collect())->where('level', $lv)->count()])->all()),
            'movement' => ['new' => Risk::whereYear('created_at', now()->year)->count(), 'closed' => Risk::where('status', 'closed')->whereYear('closed_at', now()->year)->count(), 'treating' => $risks->where('status', 'treating')->count(),
                'above_target' => $risks->filter(fn ($r) => $r->residual_score > $r->target_score)->count(), 'up' => $risks->where('trend', 'up')->count(), 'down' => $risks->where('trend', 'down')->count(), 'flat' => $risks->where('trend', 'flat')->count(),
                'inherent' => collect(['very_high', 'high', 'medium', 'low'])->mapWithKeys(fn ($lv) => [$lv => $risks->filter(fn ($r) => $byLv($r->inherent_score) === $lv)->count()])],
            'appetite_statement' => collect($request->user()->organization?->settings ?? [])->only('appetite_statement', 'appetite_basis', 'appetite_date'),
            'ai_summary' => app(\App\Services\AiService::class)->available() ? app(\App\Services\AiService::class)->dashboardSummary(['total' => $risks->count(), 'high' => $risks->whereIn('residual_level', ['high', 'very_high'])->count(), 'escalate' => $risks->whereIn('evaluation', ['escalate', 'critical'])->count(),
                'realization' => $this->metrics($risks)['realization'], 'overdue' => ActionPlan::whereNull('cancelled_at')->where('progress', '<', 100)->where('due_date', '<', now()->startOfDay())->count(), 'kri_breach' => Kri::whereIn('status', ['warning', 'critical'])->count(),
                'up' => $risks->where('trend', 'up')->count(), 'down' => $risks->where('trend', 'down')->count()], $risks->sortByDesc('residual_score')->take(5)->map(fn ($r) => $r->only('code', 'name', 'residual_score'))->values()->all(), 'exec:' . $request->user()->organization_id) : null,
            'summary' => [
                'total' => $risks->count(),
                'very_high' => $risks->where('residual_level', 'very_high')->count(),
                'high' => $risks->where('residual_level', 'high')->count(),
                'medium' => $risks->where('residual_level', 'medium')->count(),
                'low' => $risks->where('residual_level', 'low')->count(),
                'escalate' => $risks->whereIn('evaluation', ['escalate', 'critical'])->count(),
                'avg' => round((float) $risks->avg('residual_score'), 1),
                'avg_inherent' => round((float) $risks->avg('inherent_score'), 1),
                'plan_done' => $plans->where('progress', 100)->count(),
                'plan_total' => $plans->count(),
                'loss_ytd' => (float) Incident::whereYear('occurred_at', now()->year)->sum('loss_amount'),
                'incidents_ytd' => Incident::whereYear('occurred_at', now()->year)->count(),
            ],
            'appetite' => $cats,
            'trend' => $trend,
            'top' => $risks->sortByDesc('residual_score')->take(10)->values()->map(fn ($r) => $r->only('id', 'code', 'name', 'residual_score', 'residual_level', 'evaluation', 'trend', 'treatment') + ['unit' => $r->unit?->name, 'category' => $r->category?->name]),
            'units' => OrgUnit::withCount(['risks' => fn ($q) => $q->where('status', '!=', 'closed')])->with(['risks' => fn ($q) => $q->where('status', '!=', 'closed')->select('id', 'unit_id', 'residual_score', 'residual_level')])->get()
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'n' => $u->risks_count, 'high' => $u->risks->whereIn('residual_level', ['high', 'very_high'])->count(), 'avg' => round((float) $u->risks->avg('residual_score'), 1)])->filter(fn ($u) => $u['n'] > 0)->values(),
        ]);
    }

    private function scopedKris()
    {
        $u = request()->user();
        return Kri::query()->when($u->isUnitScoped(), fn ($q) => $q->whereIn('risk_id', \App\Support\UnitScope::riskIdsQuery($u)));
    }

    private function scopedControls()
    {
        $ids = request()->user()->accessibleUnitIds();
        return Control::query()->when($ids !== null, fn ($q) => $q->where(fn ($w) => $w->whereNull('unit_id')->orWhereIn('unit_id', $ids)));
    }
}

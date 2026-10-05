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
        $risks = $this->scopeUnits(Risk::query())->where('status', '!=', 'closed')->with(['unit:id,name', 'category:id,name', 'owner:id,name'])->get();
        $byLevel = ['low' => 0, 'medium' => 0, 'high' => 0, 'very_high' => 0];
        $heat = [];
        foreach ($risks as $r) {
            $byLevel[$r->residual_level] = ($byLevel[$r->residual_level] ?? 0) + 1;
            $heat["{$r->residual_l}-{$r->residual_i}"] = ($heat["{$r->residual_l}-{$r->residual_i}"] ?? 0) + 1;
        }
        $plans = $this->scopeUnits(ActionPlan::query())->whereNull('cancelled_at')->get();
        $overdue = $plans->filter(fn ($p) => $p->computedStatus() === 'overdue');
        $periods = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->format('Y-m'));
        $trend = RiskSnapshot::whereIn('period', $periods)->when($request->user()->isUnitScoped(), fn ($q) => $q->whereIn('risk_id', \App\Support\UnitScope::riskIdsQuery($request->user())))->selectRaw('period, avg(residual_score) as avg_score, count(*) as n')->groupBy('period')->orderBy('period')->get()->keyBy('period');

        return Inertia::render('Dashboard/Index', [
            'kpi' => [
                'total' => $risks->count(),
                'high' => $byLevel['high'] + $byLevel['very_high'],
                'critical' => $risks->where('evaluation', 'critical')->count(),
                'plans_total' => $plans->count(),
                'plans_overdue' => $overdue->count(),
                'kri_breach' => $this->scopedKris()->where('active', true)->whereIn('status', ['warning', 'critical'])->count(),
                'incidents_open' => $this->scopeUnits(Incident::query())->where('status', '!=', 'closed')->count(),
                'pending_approvals' => \App\Support\UnitScope::morph(Approval::query(), $request->user(), false)->where('status', 'pending')->count(),
                'controls_weak' => $this->scopedControls()->where('active', true)->where(fn ($q) => $q->where('operating_eff', '<=', 2)->orWhere('design_eff', '<=', 2))->count(),
            ],
            'by_level' => $byLevel,
            'heat' => $heat,
            'by_category' => RiskCategory::withCount(['risks' => fn ($q) => $this->scopeUnits($q)->where('status', '!=', 'closed')])->orderByDesc('risks_count')->get(['id', 'name'])->map(fn ($c) => ['name' => $c->name, 'n' => $c->risks_count]),
            'by_unit' => OrgUnit::when($request->user()->accessibleUnitIds(), fn ($q, $ids) => $q->whereIn('id', $ids))->withCount(['risks' => fn ($q) => $q->where('status', '!=', 'closed')])->get(['id', 'name'])->filter(fn ($u) => $u->risks_count > 0)->sortByDesc('risks_count')->take(8)->values()->map(fn ($u) => ['name' => $u->name, 'n' => $u->risks_count]),
            'top_risks' => $risks->sortByDesc('residual_score')->take(8)->values()->map(fn ($r) => $r->only('id', 'code', 'name', 'residual_score', 'residual_level', 'evaluation', 'trend', 'status') + ['unit' => $r->unit?->name, 'owner' => $r->owner?->name]),
            'trend' => $periods->map(fn ($p) => ['period' => $p, 'avg' => round((float) ($trend[$p]->avg_score ?? 0), 1), 'n' => (int) ($trend[$p]->n ?? 0)]),
            'alerts' => \App\Support\UnitScope::morph(Alert::query(), $request->user())->whereNull('handled_at')->latest()->limit(6)->get(['id', 'type', 'severity', 'title', 'link', 'created_at', 'read_at']),
            'upcoming' => $plans->filter(fn ($p) => in_array($p->computedStatus(), ['overdue', 'running', 'todo']))->sortBy('due_date')->take(6)->values()->map(fn ($p) => $p->only('id', 'code', 'title', 'due_date', 'progress') + ['status' => $p->computedStatus()]),
            'kris' => $this->scopedKris()->where('active', true)->with('risk:id,code')->orderByRaw("case status when 'critical' then 0 when 'warning' then 1 else 2 end")->limit(6)->get(),
        ]);
    }

    public function executive(Request $request)
    {
        $risks = Risk::where('status', '!=', 'closed')->with(['category:id,name,appetite,tolerance', 'unit:id,name'])->get();
        $scoring = app(Scoring::class);
        $cats = RiskCategory::where('active', true)->orderBy('sort')->get()->map(function ($c) use ($risks) {
            $rs = $risks->where('category_id', $c->id);
            $max = (int) ($rs->max('residual_score') ?? 0);
            return ['name' => $c->name, 'appetite' => $c->appetite, 'tolerance' => $c->tolerance, 'n' => $rs->count(), 'max' => $max,
                'status' => $max > $c->tolerance ? 'breach' : ($max > $c->appetite ? 'watch' : 'ok')];
        });
        $periods = collect(range(11, 0))->map(fn ($i) => now()->subMonths($i)->format('Y-m'));
        $snap = RiskSnapshot::whereIn('period', $periods)->get()->groupBy('period');
        $trend = $periods->map(fn ($p) => ['period' => $p,
            'high' => $snap->get($p, collect())->whereIn('level', ['high', 'very_high'])->count(),
            'total' => $snap->get($p, collect())->count(),
            'avg' => round((float) $snap->get($p, collect())->avg('residual_score'), 1)]);
        $plans = ActionPlan::whereNull('cancelled_at')->get();
        return Inertia::render('Dashboard/Executive', [
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
                ->map(fn ($u) => ['name' => $u->name, 'n' => $u->risks_count, 'high' => $u->risks->whereIn('residual_level', ['high', 'very_high'])->count(), 'avg' => round((float) $u->risks->avg('residual_score'), 1)])->filter(fn ($u) => $u['n'] > 0)->values(),
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

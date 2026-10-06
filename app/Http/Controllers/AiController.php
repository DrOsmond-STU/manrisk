<?php

namespace App\Http\Controllers;

use App\Models\Risk;
use App\Services\AiService;
use App\Support\UnitScope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AiController extends Controller
{
    public function index(Request $request, AiService $ai)
    {
        abort_if($request->user()->role === 'auditor', 403, 'Auditor tidak menggunakan AI Assistant (§3.2).');
        return Inertia::render('Ai/Index', ['enabled' => $ai->available(), 'provider' => $ai->hasProvider() ? config('manrisk.ai.model') : 'mode lokal (tanpa kunci API)', 'remaining' => $ai->remaining($request->user()), 'risks' => $this->riskOptions(),
            'categories' => \App\Models\RiskCategory::where('active', true)->orderBy('sort')->get(['id', 'name'])]);
    }

    public function run(Request $request, AiService $ai)
    {
        abort_if($request->user()->role === 'auditor', 403);
        $data = $request->validate([
            'feature' => ['required', Rule::in(['suggest_risk', 'suggest_controls', 'suggest_treatment', 'explain_score', 'summarize', 'draft_report', 'identify', 'statement'])],
            'risk_id' => ['nullable', 'integer'],
            'context' => ['nullable', 'string', 'max:3000'],
        ]);
        $input = ['context' => strip_tags($data['context'] ?? '')];
        if (!empty($data['risk_id'])) {
            $risk = Risk::with('category')->findOrFail($data['risk_id']);
            abort_unless($request->user()->can('view', $risk), 403);
            $input['risk'] = $risk->only('code', 'name', 'cause', 'event', 'impact', 'existing_controls', 'treatment', 'inherent_l', 'inherent_i', 'inherent_score', 'residual_l', 'residual_i', 'residual_score', 'residual_level', 'target_score', 'evaluation')
                + ['category' => $risk->category?->name, 'appetite' => $risk->category?->appetite, 'tolerance' => $risk->category?->tolerance];
        }
        if (in_array($data['feature'], ['summarize', 'draft_report'], true)) {
            $risks = $this->scopeUnits(Risk::query())->where('status', '!=', 'closed')->get();
            // action plan & insiden dibatasi ke cakupan unit pengguna (sama seperti daftar risikonya)
            $units = $request->user()->accessibleUnitIds();
            $plans = \App\Models\ActionPlan::whereNull('cancelled_at')->when($units !== null, fn ($q) => $q->where(fn ($w) => $w->whereIn('risk_id', UnitScope::riskIdsQuery($request->user()))->orWhereIn('unit_id', $units)))->get();
            $incidents = fn () => \App\Models\Incident::whereYear('occurred_at', now()->year)->when($units !== null, fn ($q) => $q->whereIn('unit_id', $units));
            $input['summary'] = ['total' => $risks->count(), 'high' => $risks->whereIn('residual_level', ['high', 'very_high'])->count(), 'avg' => round((float) $risks->avg('residual_score'), 1),
                'escalate' => $risks->whereIn('evaluation', ['escalate', 'critical'])->count(), 'plan_done' => $plans->where('progress', 100)->count(), 'plan_total' => $plans->count(),
                'incidents_ytd' => $incidents()->count(), 'loss_ytd' => (float) $incidents()->sum('loss_amount')];
            $input['top'] = $risks->sortByDesc('residual_score')->take(10)->values()->map(fn ($r) => $r->only('code', 'name', 'residual_score', 'residual_level', 'evaluation'))->all();
            $refs = $risks->pluck('id', 'code');
        }
        if (in_array($data['feature'], AiService::STRUCTURED, true)) {
            $input['categories'] = \App\Models\RiskCategory::where('active', true)->orderBy('sort')->pluck('name')->all();
            return response()->json($ai->structured($request->user(), $data['feature'], $input));
        }
        // peta kode → id risiko (hanya risiko dalam cakupan) agar kode yang disebut AI dapat ditautkan ke halaman risikonya
        return response()->json($ai->run($request->user(), $data['feature'], $input) + ['refs' => $refs ?? (isset($risk) ? [$risk->code => $risk->id] : [])]);
    }
}

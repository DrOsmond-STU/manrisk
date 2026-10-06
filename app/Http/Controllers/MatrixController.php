<?php

namespace App\Http\Controllers;

use App\Models\CriteriaVersion;
use App\Models\Risk;
use App\Support\Scoring;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Heatmap 5×5 inheren/residual/target dan daftar evaluasi (§4.6, §4.7). */
class MatrixController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Risk::class);
        [$base, $f] = $this->base($request);
        $risks = $base->with(['unit:id,name', 'category:id,name,appetite,tolerance', 'owner:id,name'])
            ->get(['id', 'code', 'name', 'unit_id', 'category_id', 'owner_id', 'inherent_l', 'inherent_i', 'residual_l', 'residual_i', 'target_l', 'target_i', 'inherent_score', 'residual_score', 'target_score', 'residual_level', 'evaluation', 'trend', 'treatment', 'status']);
        return Inertia::render('Risks/Matrix', $this->scopeProps($f) + [
            'risks' => $risks,
            'matrix' => CriteriaVersion::current()?->matrix ?: Scoring::defaultMatrix(),
            'criteria' => CriteriaVersion::current()?->only('likelihood', 'impact', 'thresholds'),
        ]);
    }

    public function evaluation(Request $request)
    {
        $this->authorize('viewAny', Risk::class);
        [$base, $f] = $this->base($request);
        $risks = $base->with(['unit:id,name', 'category:id,name,appetite,tolerance', 'owner:id,name'])->orderByDesc('residual_score')->get();
        $counts = array_fill_keys(array_keys(Scoring::EVALUATIONS), 0);
        foreach ($risks as $r) {
            $counts[$r->evaluation] = ($counts[$r->evaluation] ?? 0) + 1;
        }
        return Inertia::render('Risks/Evaluation', $this->scopeProps($f) + ['risks' => $risks, 'counts' => $counts]);
    }

    /** Monitoring residual: tren per risiko dari snapshot bulanan. */
    public function residual(Request $request)
    {
        $this->authorize('viewAny', Risk::class);
        $periods = collect(range(11, 0))->map(fn ($i) => now()->subMonths($i)->format('Y-m'));
        [$base, $f] = $this->base($request);
        $risks = $base->with(['snapshots' => fn ($q) => $q->whereIn('period', $periods)->orderBy('period'), 'unit:id,name'])->orderByDesc('residual_score')->get();
        return Inertia::render('Risks/Residual', $this->scopeProps($f) + [
            'periods' => $periods,
            'risks' => $risks->map(fn ($r) => $r->only('id', 'code', 'name', 'unit_id', 'inherent_score', 'residual_score', 'target_score', 'residual_level', 'trend', 'previous_score') + [
                'unit' => $r->unit?->name,
                'series' => $periods->map(fn ($p) => optional($r->snapshots->firstWhere('period', $p))->residual_score),
            ]),
        ]);
    }

    /** Risiko aktif dalam cakupan pengguna + filter yang sama dengan Risk Register (unit termasuk sub-unit). */
    private function base(Request $request): array
    {
        $f = $request->validate(['unit_id' => ['nullable', 'integer'], 'category_id' => ['nullable', 'integer'], 'objective_id' => ['nullable', 'integer'], 'owner_id' => ['nullable', 'integer'], 'process_id' => ['nullable', 'integer']]);
        $q = $this->scopeUnits(Risk::query())->where('status', '!=', 'closed');
        if (!empty($f['unit_id'])) {
            $q->whereIn('unit_id', \App\Models\OrgUnit::find($f['unit_id'])?->descendantIds() ?? [0]);
        }
        foreach (['category_id', 'objective_id', 'owner_id', 'process_id'] as $k) {
            if (!empty($f[$k])) {
                $q->where($k, $f[$k]);
            }
        }
        return [$q, $f];
    }

    private function scopeProps(array $f): array
    {
        return ['filters' => $f, 'units' => $this->unitOptions(true), 'categories' => \App\Models\RiskCategory::orderBy('sort')->get(['id', 'name'])];
    }
}

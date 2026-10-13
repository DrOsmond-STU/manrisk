<?php

namespace App\Http\Controllers;

use App\Models\CriteriaVersion;
use App\Models\Risk;
use App\Models\RiskCategory;
use App\Support\Scoring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Kriteria risiko (skala L/I, matriks, ambang) dan taksonomi kategori dengan appetite/tolerance. */
class CriteriaController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', CriteriaVersion::class);
        return Inertia::render('Criteria/Index', [
            'versions' => CriteriaVersion::with('creator:id,name')->orderByDesc('version')->get(),
            // Jumlah risiko mengikuti cakupan unit pengguna agar sama dengan daftar yang dibuka lewat tautannya
            'categories' => $this->categoryTree(),
            'tiers' => RiskCategory::TIERS,
            'default_matrix' => Scoring::defaultMatrix(),
            'default_matrices' => collect(Scoring::SCHEMES)->map(fn ($s, $k) => Scoring::defaultMatrix($k)),
            'schemes' => collect(Scoring::SCHEMES)->map(fn ($s) => ['label' => $s['label'], 'levels' => $s['levels']]),
            'appetite' => collect(auth()->user()->organization?->settings ?? [])->only('appetite_statement', 'appetite_basis', 'appetite_date'),
            'can' => ['write' => auth()->user()->can('create', CriteriaVersion::class), 'delete' => auth()->user()->can('delete', new RiskCategory())],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', CriteriaVersion::class);
        $data = $request->validate([
            'effective_from' => ['required', 'date'],
            'scheme' => ['nullable', Rule::in(array_keys(Scoring::SCHEMES))],
            'likelihood' => ['required', 'array', 'size:5'],
            'likelihood.*.v' => ['required', 'integer', 'between:1,5'],
            'likelihood.*.label' => ['required', 'string', 'max:60'],
            'likelihood.*.desc' => ['nullable', 'string', 'max:500'],
            'impact' => ['required', 'array', 'size:5'],
            'impact.*.v' => ['required', 'integer', 'between:1,5'],
            'impact.*.label' => ['required', 'string', 'max:60'],
            'impact.*.dims' => ['nullable', 'array'],
            'impact.*.dims.*' => ['nullable', 'string', 'max:300'],
            'dimensions' => ['required', 'array', 'min:1', 'max:8'],
            'dimensions.*.key' => ['required', 'string', 'max:20', 'regex:/^[a-z0-9_]+$/'],
            'dimensions.*.label' => ['required', 'string', 'max:60'],
            'matrix' => ['required', 'array', 'size:25'],
            'matrix.*' => ['required', Rule::in(array_keys(Scoring::SCHEMES[is_string($request->input('scheme')) ? $request->input('scheme') : 'standard']['levels'] ?? Scoring::LEVELS))],
            'thresholds.escalate' => ['required', 'integer', 'between:2,25'],
            'thresholds.critical' => ['required', 'integer', 'between:2,25', 'gte:thresholds.escalate'],
            'activate' => ['nullable', 'boolean'],
        ]);
        $version = DB::transaction(function () use ($data, $request) {
            $next = (int) CriteriaVersion::max('version') + 1;
            if (!empty($data['activate'])) {
                CriteriaVersion::where('active', true)->update(['active' => false]);
            }
            return CriteriaVersion::create([
                'version' => $next, 'scheme' => $data['scheme'] ?? 'standard', 'effective_from' => $data['effective_from'], 'likelihood' => $data['likelihood'], 'impact' => $data['impact'],
                'dimensions' => $data['dimensions'], 'matrix' => $data['matrix'], 'thresholds' => $data['thresholds'], 'active' => !empty($data['activate']), 'created_by' => $request->user()->id,
            ]);
        });
        if ($version->active) {
            $this->recalculateAll();
        }
        return $this->ok("Kriteria versi {$version->version} disimpan.");
    }

    public function activate(CriteriaVersion $criteria)
    {
        $this->authorize('update', $criteria);
        DB::transaction(function () use ($criteria) {
            CriteriaVersion::where('active', true)->update(['active' => false]);
            $criteria->update(['active' => true]);
        });
        $this->recalculateAll();
        return $this->ok("Kriteria versi {$criteria->version} diaktifkan dan seluruh skor dihitung ulang.");
    }

    private function recalculateAll(): void
    {
        $criteria = CriteriaVersion::current();
        $scoring = new Scoring($criteria);
        Risk::with('category')->chunkById(200, function ($risks) use ($scoring, $criteria) {
            foreach ($risks as $r) {
                $before = $r->only('residual_score', 'residual_level', 'evaluation');
                $scoring->apply($r);
                $r->criteria_version_id = $criteria?->id;
                $after = $r->only('residual_score', 'residual_level', 'evaluation');
                $r->saveQuietly();
                if ($before !== $after) {
                    // Jejak perubahan level/evaluasi akibat kriteria baru (tanpa versi penilaian baru)
                    \App\Models\AuditLog::record('recalculated', $r, collect($after)->filter(fn ($v, $k) => $before[$k] !== $v)->map(fn ($v, $k) => [$before[$k], $v])->all(), 'criteria:v' . $criteria?->version);
                }
            }
        });
    }

    /** Taksonomi berurutan pohon + jumlah risiko aktif sendiri dan agregat (beserta turunannya). */
    private function categoryTree()
    {
        $counts = $this->scopeUnits(Risk::query())->where('status', '!=', 'closed')->selectRaw('category_id, count(*) as n')->groupBy('category_id')->pluck('n', 'category_id');
        return RiskCategory::tree()->map(fn ($c) => $c->only('id', 'parent_id', 'name', 'name_en', 'bumn_code', 'description', 'appetite', 'tolerance', 'risk_limit', 'sort', 'active')
            + ['depth' => $c->depth, 'path' => $c->path, 'risks_count' => (int) ($counts[$c->id] ?? 0), 'risks_total' => (int) collect($c->descendant_ids)->sum(fn ($id) => $counts[$id] ?? 0)])->values();
    }

    // ---- Kategori ----
    public function storeCategory(Request $request)
    {
        $this->authorize('create', RiskCategory::class);
        RiskCategory::create($this->categoryRules($request));
        return $this->ok('Kategori ditambahkan.');
    }

    public function updateCategory(Request $request, RiskCategory $category)
    {
        $this->authorize('update', $category);
        $data = $this->categoryRules($request, $category);
        $category->update($data);
        // perubahan appetite/tolerance mengubah status evaluasi risiko kategori ini
        $scoring = app(Scoring::class);
        foreach ($category->risks()->get() as $r) {
            $scoring->apply($r, $category);
            $r->saveQuietly();
        }
        return $this->ok('Kategori diperbarui.');
    }

    public function destroyCategory(RiskCategory $category)
    {
        $this->authorize('delete', $category);
        if ($category->risks()->withTrashed()->exists()) {
            return back()->with('error', 'Kategori masih dipakai oleh risiko; nonaktifkan saja.');
        }
        $category->delete();
        return $this->ok('Kategori dihapus.');
    }

    private function categoryRules(Request $request, ?RiskCategory $c = null): array
    {
        $tree = RiskCategory::tree();
        $own = $c ? ($tree->firstWhere('id', $c->id)?->descendant_ids ?? [$c->id]) : [];
        // kedalaman subpohon kategori ini (1 bila tanpa turunan), agar seluruh pohon tetap ≤ 3 tingkat (T1–T3)
        $subDepth = $c ? $tree->whereIn('id', $own)->max('depth') - ($tree->firstWhere('id', $c->id)?->depth ?? 1) + 1 : 1;
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'name_en' => ['nullable', 'string', 'max:120'],
            'bumn_code' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:1000'],
            'parent_id' => ['nullable', Rule::exists('risk_categories', 'id')->where('organization_id', $request->user()->organization_id), Rule::notIn($own ?: [$c?->id]),
                function ($attr, $value, $fail) use ($tree, $subDepth) {
                    $parentDepth = (int) ($tree->firstWhere('id', (int) $value)?->depth ?? 0);
                    if ($value && $parentDepth + $subDepth > RiskCategory::MAX_DEPTH) {
                        $fail('Taksonomi paling banyak 3 tingkat (T1 Tema, T2 Kategori, T3 Kelompok Peristiwa).');
                    }
                }],
            'risk_limit' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999'],
            'appetite' => ['required', 'integer', 'between:1,25'],
            'tolerance' => ['required', 'integer', 'between:1,25', 'gte:appetite'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);
    }
}

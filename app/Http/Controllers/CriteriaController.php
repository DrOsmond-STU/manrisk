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
            'categories' => RiskCategory::withCount(['risks' => fn ($q) => $q->where('status', '!=', 'closed')])->orderBy('sort')->get(),
            'default_matrix' => Scoring::defaultMatrix(),
            'can' => ['write' => auth()->user()->can('create', CriteriaVersion::class), 'delete' => auth()->user()->can('delete', new RiskCategory())],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', CriteriaVersion::class);
        $data = $request->validate([
            'effective_from' => ['required', 'date'],
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
            'matrix.*' => ['required', Rule::in(array_keys(Scoring::LEVELS))],
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
                'version' => $next, 'effective_from' => $data['effective_from'], 'likelihood' => $data['likelihood'], 'impact' => $data['impact'],
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
        $scoring = new Scoring(CriteriaVersion::current());
        Risk::with('category')->chunkById(200, function ($risks) use ($scoring) {
            foreach ($risks as $r) {
                $scoring->apply($r);
                $r->saveQuietly();
            }
        });
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
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'name_en' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'parent_id' => ['nullable', Rule::exists('risk_categories', 'id')->where('organization_id', $request->user()->organization_id), Rule::notIn([$c?->id])],
            'appetite' => ['required', 'integer', 'between:1,25'],
            'tolerance' => ['required', 'integer', 'between:1,25', 'gte:appetite'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);
    }
}

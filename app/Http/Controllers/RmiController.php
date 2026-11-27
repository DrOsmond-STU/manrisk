<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\RmiAssessment;
use App\Models\RmiParameter;
use App\Models\RmiScore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Penilaian Risk Maturity Index (PER-2/MBU/03/2023): penilaian internal tahunan dan penilaian independen
 * paling sedikit sekali dalam tiga tahun, atas lima dimensi kematangan penerapan manajemen risiko.
 */
class RmiController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', RmiAssessment::class);
        $assessments = RmiAssessment::with('creator:id,name')->orderByDesc('year')->orderByDesc('id')->get();
        $year = (int) now()->year;
        $lastIndependent = $assessments->where('type', 'independent')->where('status', 'final')->max('year');
        $every = (int) config('manrisk.rmi.independent_every_years');
        $user = $request->user();
        return Inertia::render('Rmi/Index', [
            'assessments' => $this->paginateCollection($assessments, 10),
            'parameters' => RmiParameter::orderBy('dimension')->orderBy('sort')->orderBy('code')->get(),
            'dimensions' => config('manrisk.rmi.dimensions'),
            'phases' => config('manrisk.rmi.phases'),
            'compliance' => [
                'internal_this_year' => $assessments->where('type', 'internal')->where('year', $year)->first()?->only('id', 'status', 'score', 'phase'),
                'last_independent' => $lastIndependent,
                'independent_due' => $lastIndependent === null || $year - (int) $lastIndependent >= $every,
                'every' => $every,
                'year' => $year,
            ],
            'latest' => $assessments->where('status', 'final')->first()?->compute(),
            'can' => ['write' => $user->can('create', RmiAssessment::class), 'delete' => $user->can('delete', new RmiAssessment()), 'param' => $user->can('create', RmiParameter::class)],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', RmiAssessment::class);
        $data = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'type' => ['required', Rule::in(['internal', 'independent'])],
            'assessor' => ['nullable', 'string', 'max:160'],
            'assessed_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:4000'],
        ]);
        $params = RmiParameter::where('active', true)->pluck('id');
        if ($params->isEmpty()) {
            return back()->with('error', 'Tambahkan parameter penilaian terlebih dahulu (tab Parameter).');
        }
        $a = DB::transaction(function () use ($data, $params, $request) {
            $a = new RmiAssessment($data);
            $a->forceFill(['status' => 'draft', 'created_by' => $request->user()->id])->save();
            foreach ($params as $id) {
                RmiScore::create(['assessment_id' => $a->id, 'parameter_id' => $id]);
            }
            return $a;
        });
        return redirect()->route('rmi.show', $a)->with('success', 'Penilaian dibuat. Isi skor setiap parameter.');
    }

    public function show(RmiAssessment $assessment)
    {
        $this->authorize('view', $assessment);
        $assessment->load(['creator:id,name', 'scores.parameter']);
        return Inertia::render('Rmi/Show', [
            'assessment' => $assessment,
            'result' => $assessment->compute(),
            'dimensions' => config('manrisk.rmi.dimensions'),
            'phases' => config('manrisk.rmi.phases'),
            'previous' => RmiAssessment::where('id', '!=', $assessment->id)->where('status', 'final')->where('year', '<', $assessment->year)->orderByDesc('year')->first()?->compute(),
            'can' => ['edit' => auth()->user()->can('update', $assessment) && $assessment->status === 'draft', 'delete' => auth()->user()->can('delete', $assessment) && $assessment->status === 'draft'],
        ]);
    }

    public function saveScores(Request $request, RmiAssessment $assessment)
    {
        $this->authorize('update', $assessment);
        abort_if($assessment->status !== 'draft', 422, 'Penilaian final tidak dapat diubah.');
        $data = $request->validate([
            'scores' => ['required', 'array', 'max:500'],
            'scores.*.id' => ['required', 'integer'],
            'scores.*.score' => ['nullable', 'integer', 'between:1,5'],
            'scores.*.evidence' => ['nullable', 'string', 'max:2000'],
            'assessor' => ['nullable', 'string', 'max:160'],
            'assessed_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:4000'],
        ]);
        DB::transaction(function () use ($data, $assessment) {
            $before = $assessment->scores()->with('parameter:id,code')->get()->keyBy('id');
            $changes = [];
            foreach ($data['scores'] as $s) {
                $old = $before->get($s['id']);
                if (!$old) {
                    continue; // hanya skor milik penilaian ini
                }
                $new = ['score' => $s['score'] ?? null, 'evidence' => $s['evidence'] ?? null];
                if ($old->score !== $new['score'] || (string) $old->evidence !== (string) $new['evidence']) {
                    $changes[$old->parameter?->code ?? "#{$old->id}"] = [$old->score, $new['score']];
                    $old->update($new);
                }
            }
            if ($changes) {
                AuditLog::record('scored', $assessment, $changes, 'rmi.scores');
            }
            $assessment->fill(collect($data)->only('assessor', 'assessed_at', 'note')->all());
            $assessment->refreshScore();
        });
        return $this->ok('Skor disimpan.');
    }

    public function finalize(RmiAssessment $assessment)
    {
        $this->authorize('update', $assessment);
        abort_if($assessment->status !== 'draft', 422);
        if ($assessment->scores()->whereNull('score')->exists()) {
            return back()->with('error', 'Semua parameter harus diberi skor sebelum penilaian difinalkan.');
        }
        $assessment->refreshScore();
        $assessment->forceFill(['status' => 'final', 'finalized_at' => now()])->save();
        AuditLog::record('finalized', $assessment, ['score' => [null, $assessment->score], 'phase' => [null, $assessment->phase]], 'rmi');
        return $this->ok("Penilaian RMI {$assessment->year} difinalkan: indeks {$assessment->score}.");
    }

    public function destroy(RmiAssessment $assessment)
    {
        $this->authorize('delete', $assessment);
        abort_if($assessment->status !== 'draft', 422, 'Penilaian final tidak dapat dihapus.');
        $assessment->delete();
        return redirect()->route('rmi.index')->with('success', 'Draf penilaian dihapus.');
    }

    public function pdf(RmiAssessment $assessment)
    {
        $this->authorize('view', $assessment);
        $assessment->load(['scores.parameter', 'creator:id,name']);
        AuditLog::record('exported', $assessment, [], 'rmi.pdf');
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.rmi', ['title' => "Penilaian Risk Maturity Index {$assessment->year}", 'org' => auth()->user()->organization?->name,
            'generated_at' => now(), 'by' => auth()->user()->name, 'filter_label' => null, 'a' => $assessment, 'result' => $assessment->compute(),
            'dimensions' => config('manrisk.rmi.dimensions'), 'phases' => config('manrisk.rmi.phases')]);
        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="rmi-' . $assessment->year . '-' . $assessment->type . '.pdf"']);
    }

    // ---- Parameter ----
    public function storeParameter(Request $request)
    {
        $this->authorize('create', RmiParameter::class);
        $param = RmiParameter::create($this->paramRules($request));
        // parameter baru ikut ke penilaian yang masih draf
        RmiAssessment::where('status', 'draft')->get()->each(fn ($a) => RmiScore::firstOrCreate(['assessment_id' => $a->id, 'parameter_id' => $param->id]));
        return $this->ok('Parameter ditambahkan.');
    }

    public function updateParameter(Request $request, RmiParameter $parameter)
    {
        $this->authorize('update', $parameter);
        $parameter->update($this->paramRules($request, $parameter));
        return $this->ok('Parameter diperbarui.');
    }

    public function destroyParameter(RmiParameter $parameter)
    {
        $this->authorize('delete', $parameter);
        $usedInFinal = RmiScore::where('parameter_id', $parameter->id)->whereIn('assessment_id', RmiAssessment::where('status', 'final')->select('id'))->exists();
        if ($usedInFinal) {
            $parameter->update(['active' => false]);
            return $this->ok('Parameter dipakai penilaian final; parameter dinonaktifkan agar riwayat tetap utuh.');
        }
        $parameter->delete();
        return $this->ok('Parameter dihapus.');
    }

    private function paramRules(Request $request, ?RmiParameter $p = null): array
    {
        $data = $request->validate([
            'dimension' => ['required', Rule::in(array_keys(config('manrisk.rmi.dimensions')))],
            'code' => ['required', 'string', 'max:20', Rule::unique('rmi_parameters', 'code')->where('organization_id', $request->user()->organization_id)->ignore($p?->id)],
            'name' => ['required', 'string', 'max:255'],
            'guidance' => ['nullable', 'string', 'max:4000'],
            'weight' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'active' => ['nullable', 'boolean'],
        ]);
        return ['sort' => (int) ($data['sort'] ?? 0), 'active' => (bool) ($data['active'] ?? true)] + $data;
    }
}

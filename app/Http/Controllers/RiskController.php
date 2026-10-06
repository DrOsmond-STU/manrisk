<?php

namespace App\Http\Controllers;

use App\Http\Requests\RiskRequest;
use App\Models\Control;
use App\Models\CriteriaVersion;
use App\Models\Objective;
use App\Models\Process;
use App\Models\Risk;
use App\Models\RiskCategory;
use App\Models\RiskVersion;
use App\Services\AlertService;
use App\Services\ApprovalService;
use App\Support\Numbering;
use App\Support\Scoring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Risk Register: identifikasi, analisis, evaluasi, treatment, versi, dan alur status (§4.4–§4.9). */
class RiskController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Risk::class);
        [$q, $f] = $this->filtered($request);
        $q->with(['unit:id,name', 'category:id,name', 'owner:id,name', 'objective:id,code'])->withCount(['actionPlans', 'controls', 'kris']);
        return Inertia::render('Risks/Index', [
            'risks' => $q->paginate($f['per_page'] ?? 20)->withQueryString(),
            'filters' => $f,
            'units' => $this->unitOptions(),
            'categories' => RiskCategory::orderBy('sort')->get(['id', 'name']),
            'owners' => $this->userOptions(),
            'objectives' => Objective::orderBy('sort')->get(['id', 'code', 'name']),
            'processes' => Process::orderBy('name')->get(['id', 'name']),
            'can' => ['create' => $request->user()->can('create', Risk::class), 'import' => $request->user()->hasRole('super_admin', 'risk_admin', 'risk_manager', 'risk_officer')],
        ]);
    }

    /** Query register sesuai filter aktif (dipakai daftar, ekspor, dan tautan dari heatmap/dashboard). */
    public function filtered(Request $request): array
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'], 'unit_id' => ['nullable', 'integer'], 'category_id' => ['nullable', 'integer'],
            // level & evaluation menerima satu nilai atau daftar dipisah koma (mis. level=high,very_high)
            'level' => ['nullable', 'string', 'max:60', $this->listOf(array_keys(Scoring::LEVELS))], 'status' => ['nullable', Rule::in([...array_keys(config('manrisk.risk_statuses')), 'active'])],
            'evaluation' => ['nullable', 'string', 'max:80', $this->listOf(array_keys(Scoring::EVALUATIONS))], 'owner_id' => ['nullable', 'integer'],
            'ids' => ['nullable', 'string', 'max:4000', 'regex:/^\d+(,\d+)*$/'],
            'objective_id' => ['nullable', 'integer'], 'process_id' => ['nullable', 'integer'], 'trend' => ['nullable', Rule::in(['up', 'flat', 'down'])],
            'mode' => ['nullable', Rule::in(['inherent', 'residual', 'target'])], 'l' => ['nullable', 'integer', 'between:1,5'], 'i' => ['nullable', 'integer', 'between:1,5'],
            'no_controls' => ['nullable', 'boolean'], 'period' => ['nullable', 'date_format:Y'],
            'sort' => ['nullable', Rule::in(['code', 'name', 'residual_score', 'inherent_score', 'updated_at', 'due_date'])], 'dir' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'between:10,100'],
        ]);
        $q = $this->scopeUnits(Risk::query());
        if (!empty($f['q'])) {
            $term = '%' . addcslashes($f['q'], '%_\\') . '%';
            $q->where(fn ($w) => $w->where('code', 'like', $term)->orWhere('name', 'like', $term)->orWhere('event', 'like', $term)->orWhere('cause', 'like', $term)->orWhere('impact', 'like', $term));
        }
        foreach (['level' => 'residual_level', 'evaluation' => 'evaluation'] as $k => $col) {
            if (!empty($f[$k])) {
                $q->whereIn($col, explode(',', $f[$k]));
            }
        }
        if (!empty($f['ids'])) {
            $q->whereIn('id', array_slice(array_map('intval', explode(',', $f['ids'])), 0, 500));
        }
        foreach (['category_id', 'owner_id', 'objective_id', 'process_id', 'trend'] as $k) {
            if (!empty($f[$k])) {
                $q->where($k, $f[$k]);
            }
        }
        if (!empty($f['unit_id'])) {
            $unit = \App\Models\OrgUnit::find($f['unit_id']);
            $q->whereIn('unit_id', $unit ? $unit->descendantIds() : [0]);
        }
        if (!empty($f['status'])) {
            $f['status'] === 'active' ? $q->where('status', '!=', 'closed') : $q->where('status', $f['status']);
        }
        if (!empty($f['l']) && !empty($f['i'])) {
            $m = $f['mode'] ?? 'residual';
            $q->where("{$m}_l", $f['l'])->where("{$m}_i", $f['i'])->where('status', '!=', 'closed');
        }
        if (!empty($f['no_controls'])) {
            $q->doesntHave('controls')->where('status', '!=', 'closed');
        }
        if (!empty($f['period'])) {
            $q->whereYear('created_at', $f['period']);
        }
        $q->orderBy($f['sort'] ?? 'residual_score', $f['dir'] ?? 'desc')->orderBy('code');
        return [$q, $f];
    }

    private function threshold(string $key): int
    {
        return (int) (CriteriaVersion::current()?->thresholds[$key] ?? ['escalate' => 16, 'critical' => 20][$key]);
    }

    /** Aturan validasi: satu nilai atau daftar dipisah koma dari pilihan yang diizinkan. */
    private function listOf(array $allowed): \Closure
    {
        return function (string $attr, $value, \Closure $fail) use ($allowed) {
            if (array_diff(explode(',', (string) $value), $allowed)) {
                $fail("Nilai {$attr} tidak valid.");
            }
        };
    }

    /** Ekspor register sesuai filter aktif (F-REG-06). */
    public function export(Request $request)
    {
        $this->authorize('viewAny', Risk::class);
        $format = $request->validate(['format' => ['required', Rule::in(['xlsx', 'pdf'])]])['format'];
        [$q, $f] = $this->filtered($request);
        $risks = $q->with(['unit:id,name', 'category:id,name', 'owner:id,name', 'objective:id,code,name', 'controls:id,code', 'actionPlans:id,risk_id,code,progress,cancelled_at'])->limit(5000)->get();
        $data = ['title' => 'Risk Register', 'org' => $request->user()->organization?->name, 'generated_at' => now(), 'by' => $request->user()->name, 'risks' => $risks, 'params' => $f];
        $file = 'risk-register-' . now()->format('Ymd-His') . '.' . $format;
        \App\Models\AuditLog::record('exported', $request->user(), ['filters' => [null, $f]], 'risks.export');
        if ($format === 'xlsx') {
            return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\ReportExport('register', $data), $file);
        }
        return \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.register', $data)->setPaper('a4', 'landscape')->download($file);
    }

    /** Deteksi duplikat (F-IDN-07): risiko serupa (≥ 80 %) pada unit yang sama. */
    public function similar(Request $request)
    {
        $this->authorize('viewAny', Risk::class);
        $d = $request->validate(['unit_id' => ['required', 'integer'], 'text' => ['required', 'string', 'max:2000'], 'except' => ['nullable', 'integer']]);
        return response()->json(\App\Support\Duplicates::find($d['unit_id'], $d['text'], $d['except'] ?? null, $request->user()));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Risk::class);
        // Prefill dari menu lain: sasaran/unit/proses (Pemetaan Sasaran, Struktur Organisasi) atau saran AI
        $q = $request->validate(['name' => ['nullable', 'string', 'max:255'], 'cause' => ['nullable', 'string', 'max:2000'], 'event' => ['nullable', 'string', 'max:2000'], 'impact' => ['nullable', 'string', 'max:2000'],
            'category_id' => ['nullable', 'integer'], 'unit_id' => ['nullable', 'integer'], 'objective_id' => ['nullable', 'integer'], 'process_id' => ['nullable', 'integer']]);
        $prefill = array_filter($q, fn ($v) => $v !== null && $v !== '');
        if (!empty($prefill['process_id']) && ($proc = Process::find($prefill['process_id']))) {
            $prefill += ['unit_id' => $proc->unit_id];
        }
        if ($id = $request->integer('incident')) {
            $inc = \App\Models\Incident::findOrFail($id);
            $this->authorize('view', $inc);
            $prefill = ['name' => $inc->title, 'unit_id' => $inc->unit_id, 'cause' => $inc->cause, 'event' => $inc->title, 'impact' => $inc->impact, 'existing_controls' => $inc->corrective_action, 'from_incident' => $inc->id, 'incident_code' => $inc->code] + $prefill;
        }
        return Inertia::render('Risks/Form', $this->formProps() + ['risk' => null, 'prefill' => $prefill, 'ai' => app(\App\Services\AiService::class)->available() && $request->user()->role !== 'auditor']);
    }

    public function store(RiskRequest $request)
    {
        $this->authorize('create', Risk::class);
        $data = $request->validated();
        $risk = DB::transaction(function () use ($data, $request) {
            $risk = new Risk(collect($data)->except(['control_ids', 'note', 'plan_title', 'plan_due', 'plan_pic_id', 'from_incident'])->all());
            $risk->code = Numbering::next(Risk::class, 'R');
            $risk->status = 'draft';
            $risk->version = 1;
            $risk->criteria_version_id = CriteriaVersion::current()?->id;
            $risk->created_by = $request->user()->id;
            $risk->updated_by = $request->user()->id;
            app(Scoring::class)->apply($risk, RiskCategory::find($data['category_id']));
            $risk->save();
            $risk->controls()->sync($data['control_ids'] ?? []);
            $this->snapshotVersion($risk, $data['note'] ?? 'Versi awal');
            if (!empty($data['plan_title'])) {
                \App\Models\ActionPlan::create(['code' => Numbering::next(\App\Models\ActionPlan::class, 'AP'), 'risk_id' => $risk->id, 'title' => $data['plan_title'], 'pic_id' => $data['plan_pic_id'] ?? $risk->owner_id,
                    'unit_id' => $risk->unit_id, 'priority' => $risk->residual_score >= $this->threshold('escalate') ? 'critical' : ($risk->residual_score >= 10 ? 'high' : 'medium'), 'start_date' => now(), 'due_date' => $data['plan_due'],
                    'expected_dl' => max(0, $risk->residual_l - $risk->target_l), 'expected_di' => max(0, $risk->residual_i - $risk->target_i), 'created_by' => $request->user()->id]);
            }
            if (!empty($data['from_incident'])) {
                \App\Models\Incident::whereKey($data['from_incident'])->whereNull('risk_id')->update(['risk_id' => $risk->id]);
                // Loss event insiden ikut terklasifikasi ke kategori risiko yang baru dibuat
                \App\Models\LossEvent::where('incident_id', $data['from_incident'])->update(['category_id' => $risk->category_id, 'risk_name' => mb_substr($risk->name, 0, 255)]);
            }
            return $risk;
        });
        return redirect()->route('risks.show', $risk)->with('success', "Risiko {$risk->code} tersimpan sebagai draft.");
    }

    public function show(Risk $risk)
    {
        $this->authorize('view', $risk);
        $risk->load(['unit:id,name', 'category:id,name,appetite,tolerance', 'owner:id,name,role', 'objective:id,code,name', 'process:id,name', 'creator:id,name',
            'controls' => fn ($q) => $q->with('owner:id,name'), 'actionPlans' => fn ($q) => $q->with('pic:id,name')->orderBy('due_date'),
            'kris', 'incidents' => fn ($q) => $q->latest('occurred_at')->limit(10), 'improvements' => fn ($q) => $q->with('pic:id,name')->latest(), 'reviews' => fn ($q) => $q->with('reviewer:id,name')->latest(),
            'versions' => fn ($q) => $q->with(['creator:id,name', 'approver:id,name'])->orderByDesc('version'), 'documents' => fn ($q) => $q->with('uploader:id,name')->latest(),
            'approvals' => fn ($q) => $q->with(['steps.approver:id,name', 'requester:id,name'])->latest(), 'lessons' => fn ($q) => $q->with('creator:id,name')->latest(), 'snapshots']);
        $risk->loadCount('incidents');
        $scoring = app(Scoring::class);
        $user = auth()->user();
        return Inertia::render('Risks/Show', [
            'risk' => $risk,
            'alerts' => \App\Models\Alert::where('subject_type', 'risk')->where('subject_id', $risk->id)->whereNull('handled_at')->latest()->limit(5)->get(['id', 'title', 'severity', 'link', 'created_at']),
            'projected' => $scoring->projected($risk),
            'statement' => $risk->statement(),
            'audit' => \App\Models\AuditLog::where('subject_type', $risk->getMorphClass())->where('subject_id', $risk->id)->with('user:id,name')->latest('id')->limit(30)->get(),
            'criteria' => CriteriaVersion::current()?->only('likelihood', 'impact', 'dimensions', 'matrix', 'thresholds'),
            'can' => [
                'update' => $user->can('update', $risk), 'edit' => $user->can('update', $risk) && !in_array($risk->status, ['pending', 'closed'], true), 'delete' => $user->can('delete', $risk), 'submit' => $user->can('submit', $risk),
                'close' => $user->can('close', $risk), 'score' => $user->can('changeScore', $risk), 'plan' => $user->can('create', \App\Models\ActionPlan::class),
                'document' => $user->can('create', \App\Models\Document::class), 'review' => $user->can('create', \App\Models\Review::class),
            ],
        ]);
    }

    public function edit(Risk $risk)
    {
        $this->authorize('update', $risk);
        if ($risk->status === 'closed') {
            return redirect()->route('risks.show', $risk)->with('error', 'Risiko yang sudah ditutup tidak dapat diubah.');
        }
        $risk->load('controls:id');
        return Inertia::render('Risks/Form', $this->formProps() + ['risk' => $risk->toArray() + ['control_ids' => $risk->controls->pluck('id')]]);
    }

    public function update(RiskRequest $request, Risk $risk)
    {
        $this->authorize('update', $risk);
        if ($risk->status === 'pending') {
            return back()->with('error', 'Risiko sedang menunggu persetujuan dan tidak dapat diubah.');
        }
        if ($risk->status === 'closed') {
            return back()->with('error', 'Risiko yang sudah ditutup tidak dapat diubah; catat risiko baru bila muncul kembali.');
        }
        $data = $request->validated();
        $scoreChanged = false;
        DB::transaction(function () use ($data, $risk, $request, &$scoreChanged) {
            $prevScore = $risk->residual_score;
            $scoreFields = ['inherent_l', 'inherent_i', 'residual_l', 'residual_i', 'target_l', 'target_i'];
            // Nilai terakhir yang berlaku — dipulihkan bila perubahan skor ditolak/dikembalikan
            $restore = $risk->only([...$scoreFields, 'inherent_dims', 'residual_dims', 'previous_score']);
            $risk->fill(collect($data)->except(['control_ids', 'note', 'plan_title', 'plan_due', 'plan_pic_id', 'from_incident'])->all());
            $scoreChanged = $risk->isDirty($scoreFields) || $risk->isDirty(['inherent_dims', 'residual_dims']);
            if ($scoreChanged) {
                $this->authorize('changeScore', $risk);
                $risk->previous_score = $prevScore;
                $risk->version = $risk->version + 1;
            }
            $risk->updated_by = $request->user()->id;
            app(Scoring::class)->apply($risk, RiskCategory::find($data['category_id']));
            $risk->save();
            $risk->controls()->sync($data['control_ids'] ?? []);
            if ($scoreChanged) {
                $this->snapshotVersion($risk, $data['note'] ?? null);
                app(AlertService::class)->checkScoreChange($risk, $prevScore);
                if ($risk->status !== 'draft') {
                    app(ApprovalService::class)->submit($risk, 'score_change', $request->user(), $data['note'] ?? "Perubahan skor residual {$prevScore} → {$risk->residual_score}", ['restore' => $restore]);
                }
            }
        });
        return redirect()->route('risks.show', $risk)->with('success', $scoreChanged && $risk->status === 'pending' ? 'Perubahan skor diajukan untuk persetujuan.' : 'Risiko diperbarui.');
    }

    public function destroy(Risk $risk)
    {
        $this->authorize('delete', $risk);
        if ($risk->status !== 'draft') {
            return back()->with('error', 'Hanya risiko berstatus draft yang dapat dihapus; gunakan penutupan risiko.');
        }
        DB::transaction(function () use ($risk) {
            // Draft dihapus beserta turunannya agar tidak ada action plan/KRI/insiden yatim yang menunjuk risiko terhapus
            $risk->actionPlans()->get()->each->delete();
            \App\Models\Kri::where('risk_id', $risk->id)->update(['risk_id' => null]);
            \App\Models\Incident::where('risk_id', $risk->id)->update(['risk_id' => null]);
            \App\Models\Improvement::where('risk_id', $risk->id)->update(['risk_id' => null]);
            $risk->controls()->detach();
            $risk->delete();
        });
        return redirect()->route('risks.index')->with('success', "Risiko {$risk->code} dihapus.");
    }

    /** Ajukan draft risiko ke alur persetujuan (risiko baru / penerimaan risiko). */
    public function submit(Request $request, Risk $risk, ApprovalService $approvals)
    {
        $this->authorize('submit', $risk);
        $aboveAppetite = $risk->residual_score > (int) ($risk->category?->appetite ?? 6);
        $data = $request->validate(['note' => [$risk->treatment === 'retain' && $aboveAppetite ? 'required' : 'nullable', 'string', 'max:1000']], ['note.required' => 'Penerimaan risiko di atas appetite wajib disertai alasan (F-TRT-01).']);
        $type = $risk->treatment === 'retain' ? 'retain' : 'new_risk';
        $approvals->submit($risk, $type, $request->user(), $data['note'] ?? null);
        return back()->with('success', "Risiko {$risk->code} diajukan untuk persetujuan.");
    }

    /** Ajukan penutupan risiko (butuh persetujuan). */
    public function close(Request $request, Risk $risk, ApprovalService $approvals)
    {
        $this->authorize('close', $risk);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $openPlans = $risk->actionPlans()->whereNull('cancelled_at')->where('progress', '<', 100)->count();
        if ($openPlans > 0) {
            return back()->with('error', "Masih ada {$openPlans} action plan yang belum selesai.");
        }
        $approvals->submit($risk, 'closure', $request->user(), $data['reason']);
        return back()->with('success', 'Pengajuan penutupan risiko dikirim.');
    }

    /** Tambah catatan pembelajaran pada risiko. */
    public function lesson(Request $request, Risk $risk)
    {
        $this->authorize('update', $risk);
        $data = $request->validate(['text' => ['required', 'string', 'max:2000']]);
        $risk->lessons()->create(['organization_id' => $risk->organization_id, 'text' => $data['text'], 'created_by' => $request->user()->id]);
        return back()->with('success', 'Lesson learned disimpan.');
    }

    private function formProps(): array
    {
        return [
            'units' => $this->unitOptions(true),
            'categories' => RiskCategory::where('active', true)->orderBy('sort')->get(['id', 'name', 'appetite', 'tolerance']),
            'owners' => $this->userOptions(['risk_owner', 'risk_manager', 'risk_officer', 'risk_admin', 'super_admin', 'management']),
            'objectives' => Objective::where('active', true)->orderBy('sort')->get(['id', 'code', 'name']),
            'processes' => Process::orderBy('name')->get(['id', 'name', 'unit_id']),
            'controls' => Control::where('active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'criteria' => CriteriaVersion::current()?->only('likelihood', 'impact', 'dimensions', 'matrix', 'thresholds') ?? ['matrix' => Scoring::defaultMatrix(), 'dimensions' => [], 'likelihood' => [], 'impact' => []],
        ];
    }

    private function snapshotVersion(Risk $risk, ?string $note): RiskVersion
    {
        return RiskVersion::create([
            'risk_id' => $risk->id, 'version' => $risk->version,
            'inherent_l' => $risk->inherent_l, 'inherent_i' => $risk->inherent_i, 'residual_l' => $risk->residual_l, 'residual_i' => $risk->residual_i,
            'target_l' => $risk->target_l, 'target_i' => $risk->target_i,
            'snapshot' => $risk->only('name', 'cause', 'event', 'impact', 'existing_controls', 'treatment', 'treatment_note', 'inherent_score', 'residual_score', 'target_score', 'residual_level', 'evaluation'),
            'note' => $note, 'created_by' => auth()->id(),
        ]);
    }
}

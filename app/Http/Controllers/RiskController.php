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
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'], 'unit_id' => ['nullable', 'integer'], 'category_id' => ['nullable', 'integer'],
            'level' => ['nullable', Rule::in(array_keys(Scoring::LEVELS))], 'status' => ['nullable', Rule::in(array_keys(config('manrisk.risk_statuses')))],
            'evaluation' => ['nullable', Rule::in(array_keys(Scoring::EVALUATIONS))], 'owner_id' => ['nullable', 'integer'],
            'sort' => ['nullable', Rule::in(['code', 'name', 'residual_score', 'inherent_score', 'updated_at', 'due_date'])], 'dir' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'between:10,100'],
        ]);
        $q = $this->scopeUnits(Risk::query())->with(['unit:id,name', 'category:id,name', 'owner:id,name'])->withCount(['actionPlans', 'controls', 'kris']);
        if (!empty($f['q'])) {
            $q->where(fn ($w) => $w->where('code', 'like', "%{$f['q']}%")->orWhere('name', 'like', "%{$f['q']}%")->orWhere('event', 'like', "%{$f['q']}%"));
        }
        foreach (['unit_id', 'category_id', 'owner_id', 'status', 'evaluation'] as $k) {
            if (!empty($f[$k])) {
                $q->where($k, $f[$k]);
            }
        }
        if (!empty($f['level'])) {
            $q->where('residual_level', $f['level']);
        }
        $q->orderBy($f['sort'] ?? 'residual_score', $f['dir'] ?? 'desc')->orderBy('code');
        return Inertia::render('Risks/Index', [
            'risks' => $q->paginate($f['per_page'] ?? 20)->withQueryString(),
            'filters' => $f,
            'units' => $this->unitOptions(),
            'categories' => RiskCategory::orderBy('sort')->get(['id', 'name']),
            'owners' => $this->userOptions(),
            'can' => ['create' => $request->user()->can('create', Risk::class)],
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Risk::class);
        return Inertia::render('Risks/Form', $this->formProps() + ['risk' => null]);
    }

    public function store(RiskRequest $request)
    {
        $this->authorize('create', Risk::class);
        $data = $request->validated();
        $risk = DB::transaction(function () use ($data, $request) {
            $risk = new Risk(collect($data)->except(['control_ids', 'note'])->all());
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
            return $risk;
        });
        return redirect()->route('risks.show', $risk)->with('success', "Risiko {$risk->code} tersimpan sebagai draft.");
    }

    public function show(Risk $risk)
    {
        $this->authorize('view', $risk);
        $risk->load(['unit:id,name', 'category:id,name,appetite,tolerance', 'owner:id,name,role', 'objective:id,code,name', 'process:id,name', 'creator:id,name',
            'controls' => fn ($q) => $q->with('owner:id,name'), 'actionPlans' => fn ($q) => $q->with('pic:id,name')->orderBy('due_date'),
            'kris', 'incidents' => fn ($q) => $q->latest('occurred_at')->limit(10), 'reviews' => fn ($q) => $q->with('reviewer:id,name')->latest(),
            'versions' => fn ($q) => $q->with(['creator:id,name', 'approver:id,name'])->orderByDesc('version'), 'documents' => fn ($q) => $q->with('uploader:id,name')->latest(),
            'approvals' => fn ($q) => $q->with(['steps.approver:id,name', 'requester:id,name'])->latest(), 'lessons' => fn ($q) => $q->with('creator:id,name')->latest(), 'snapshots']);
        $scoring = app(Scoring::class);
        $user = auth()->user();
        return Inertia::render('Risks/Show', [
            'risk' => $risk,
            'projected' => $scoring->projected($risk),
            'statement' => $risk->statement(),
            'audit' => \App\Models\AuditLog::where('subject_type', $risk->getMorphClass())->where('subject_id', $risk->id)->with('user:id,name')->latest('id')->limit(30)->get(),
            'criteria' => CriteriaVersion::current()?->only('likelihood', 'impact', 'dimensions', 'matrix'),
            'can' => [
                'update' => $user->can('update', $risk), 'delete' => $user->can('delete', $risk), 'submit' => $user->can('submit', $risk),
                'close' => $user->can('close', $risk), 'score' => $user->can('changeScore', $risk), 'plan' => $user->can('create', \App\Models\ActionPlan::class),
                'document' => $user->can('create', \App\Models\Document::class), 'review' => $user->can('create', \App\Models\Review::class),
            ],
        ]);
    }

    public function edit(Risk $risk)
    {
        $this->authorize('update', $risk);
        $risk->load('controls:id');
        return Inertia::render('Risks/Form', $this->formProps() + ['risk' => $risk->toArray() + ['control_ids' => $risk->controls->pluck('id')]]);
    }

    public function update(RiskRequest $request, Risk $risk)
    {
        $this->authorize('update', $risk);
        if ($risk->status === 'pending') {
            return back()->with('error', 'Risiko sedang menunggu persetujuan dan tidak dapat diubah.');
        }
        $data = $request->validated();
        $scoreChanged = false;
        DB::transaction(function () use ($data, $risk, $request, &$scoreChanged) {
            $prevScore = $risk->residual_score;
            $scoreFields = ['inherent_l', 'inherent_i', 'residual_l', 'residual_i', 'target_l', 'target_i'];
            $risk->fill(collect($data)->except(['control_ids', 'note'])->all());
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
                    app(ApprovalService::class)->submit($risk, 'score_change', $request->user(), $data['note'] ?? "Perubahan skor residual {$prevScore} → {$risk->residual_score}");
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
        $risk->delete();
        return redirect()->route('risks.index')->with('success', "Risiko {$risk->code} dihapus.");
    }

    /** Ajukan draft risiko ke alur persetujuan (risiko baru / penerimaan risiko). */
    public function submit(Request $request, Risk $risk, ApprovalService $approvals)
    {
        $this->authorize('submit', $risk);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
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

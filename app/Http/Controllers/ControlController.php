<?php

namespace App\Http\Controllers;

use App\Models\Control;
use App\Models\ControlAssessment;
use App\Support\Numbering;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Manajemen kontrol & efektivitas kontrol (§4.10, §4.11). */
class ControlController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Control::class);
        $f = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'type' => ['nullable', Rule::in(['preventive', 'detective', 'corrective'])], 'eff' => ['nullable', Rule::in(['weak', 'ok'])], 'risk_id' => ['nullable', 'integer']]);
        $ids = $request->user()->accessibleUnitIds();
        $q = $this->scoped()->with(['owner:id,name', 'unit:id,name', 'risks' => fn ($r) => $r->select('risks.id', 'code', 'name', 'residual_level', 'unit_id')->when($ids !== null, fn ($x) => $x->whereIn('unit_id', $ids))])->withCount('assessments');
        if (!empty($f['q'])) {
            $q->where(fn ($w) => $w->where('code', 'like', "%{$f['q']}%")->orWhere('name', 'like', "%{$f['q']}%"));
        }
        if (!empty($f['type'])) {
            $q->where('type', $f['type']);
        }
        if (!empty($f['risk_id'])) {
            $q->whereHas('risks', fn ($r) => $r->where('risks.id', $f['risk_id']));
        }
        if (($f['eff'] ?? null) === 'weak') {
            $q->where('active', true)->where(fn ($w) => $w->where('operating_eff', '<=', 2)->orWhere('design_eff', '<=', 2)); // selaras KPI "Lemah" (kontrol aktif)
        }
        $controls = $q->orderBy('code')->get();
        $all = $this->scoped()->where('active', true)->get(['id', 'design_eff', 'operating_eff', 'type', 'next_test_at']);
        return Inertia::render('Controls/Index', [
            'controls' => $controls->map(fn ($c) => $c->toArray() + ['overall' => $c->overallEffectiveness()]),
            'filters' => $f,
            'filter_risk' => !empty($f['risk_id']) ? \App\Models\Risk::find($f['risk_id'])?->only('id', 'code', 'name') : null,
            'stats' => [
                'total' => $all->count(),
                'effective' => $all->filter(fn ($c) => ($c->overallEffectiveness() ?? 0) >= 3)->count(),
                'weak' => $all->filter(fn ($c) => $c->overallEffectiveness() !== null && $c->overallEffectiveness() <= 2)->count(),
                'untested' => $all->whereNull('operating_eff')->count(),
                'due' => $all->filter(fn ($c) => $c->next_test_at && $c->next_test_at->isPast())->count(),
                'by_type' => $all->countBy('type'),
                'risks_without_controls' => $this->scopeUnits(\App\Models\Risk::query())->where('status', '!=', 'closed')->doesntHave('controls')->count(),
            ],
            'users' => $this->userOptions(),
            'units' => $this->unitOptions(),
            'risks' => $this->riskOptions(),
            'can' => ['write' => $request->user()->can('create', Control::class), 'delete' => $request->user()->can('delete', new Control())],
        ]);
    }

    /** Kontrol yang terlihat oleh pengguna bercakupan unit: tanpa unit, unitnya dalam cakupan, atau mengendalikan risiko dalam cakupan. */
    private function scoped()
    {
        $ids = request()->user()->accessibleUnitIds();
        return Control::query()->when($ids !== null, fn ($q) => $q->where(fn ($w) => $w->whereNull('unit_id')->orWhereIn('unit_id', $ids)->orWhereHas('risks', fn ($r) => $r->whereIn('unit_id', $ids))));
    }

    public function show(Control $control)
    {
        $this->authorize('view', $control);
        $u = auth()->user();
        $ids = $u->accessibleUnitIds();
        $control->load(['owner:id,name', 'unit:id,name', 'risks' => fn ($r) => $r->select('risks.id', 'code', 'name', 'residual_score', 'residual_level', 'unit_id', 'status')->when($ids !== null, fn ($x) => $x->whereIn('unit_id', $ids)), 'assessments' => fn ($q) => $q->with('tester:id,name')->orderByDesc('tested_at'), 'documents.uploader:id,name',
            'improvements' => fn ($q) => $q->with('pic:id,name')->orderByRaw("case status when 'done' then 1 else 0 end")->latest()]);
        $plans = \App\Models\ActionPlan::whereIn('risk_id', $control->risks->pluck('id'))->with(['risk:id,code', 'pic:id,name'])->orderBy('due_date')->get();
        return Inertia::render('Controls/Show', ['control' => $control->toArray() + ['overall' => $control->overallEffectiveness()],
            'plans' => $plans->map(fn ($p) => $p->only('id', 'code', 'title', 'risk_id', 'progress', 'due_date') + ['risk' => $p->risk?->code, 'pic' => $p->pic?->name, 'status' => $p->computedStatus()]),
            'can' => ['write' => $u->can('update', $control), 'upload' => $u->can('create', \App\Models\Document::class)]]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Control::class);
        $data = $this->rules($request);
        $control = Control::create(collect($data)->except('risk_ids')->all() + ['code' => Numbering::next(Control::class, 'C'), 'created_by' => $request->user()->id]);
        $control->risks()->sync($data['risk_ids'] ?? []);
        return $this->ok("Kontrol {$control->code} ditambahkan.");
    }

    public function update(Request $request, Control $control)
    {
        $this->authorize('update', $control);
        $data = $this->rules($request);
        $control->update(collect($data)->except('risk_ids')->all());
        if (array_key_exists('risk_ids', $data)) {
            // pengguna bercakupan unit hanya melihat/memilih risiko unitnya: tautan ke risiko unit lain dipertahankan
            $ids = $request->user()->accessibleUnitIds();
            $keep = $ids === null ? [] : $control->risks()->whereNotIn('unit_id', $ids)->pluck('risks.id')->all();
            $control->risks()->sync(array_values(array_unique([...array_map('intval', $data['risk_ids'] ?? []), ...$keep])));
        }
        return $this->ok('Kontrol diperbarui.');
    }

    public function destroy(Control $control)
    {
        $this->authorize('delete', $control);
        $control->delete();
        return $this->ok('Kontrol dihapus.');
    }

    /** Catat hasil pengujian kontrol (desain & operasi) → memperbarui efektivitas terkini. */
    public function assess(Request $request, Control $control)
    {
        $this->authorize('update', $control);
        $data = $request->validate([
            'tested_at' => ['required', 'date', 'before_or_equal:today'],
            'design_eff' => ['required', 'integer', 'between:1,4'],
            'operating_eff' => ['required', 'integer', 'between:1,4'],
            'note' => ['nullable', 'string', 'max:2000'],
            'next_test_at' => ['nullable', 'date', 'after:tested_at'],
        ]);
        ControlAssessment::create(['control_id' => $control->id, 'tester_id' => $request->user()->id] + collect($data)->only('tested_at', 'design_eff', 'operating_eff', 'note')->all());
        $control->update(['design_eff' => $data['design_eff'], 'operating_eff' => $data['operating_eff'], 'last_tested_at' => $data['tested_at'],
            'next_test_at' => $data['next_test_at'] ?? $this->nextTest($control->frequency, $data['tested_at'])]);
        $alerts = app(\App\Services\AlertService::class);
        $alerts->resolve($control, ['control_due']); // pengujian sudah dilakukan
        if (min($data['design_eff'], $data['operating_eff']) >= 3) {
            // kembali efektif → peringatan kegagalan (pada kontrol & risiko terkait) selesai
            $alerts->resolve($control, ['control_failure']);
            \App\Models\Alert::withoutGlobalScopes()->where('organization_id', $control->organization_id)->where('type', 'control_failure')->where('dedupe_key', 'like', "ctlfail:{$control->id}:%")
                ->whereNull('handled_at')->update(['handled_at' => now(), 'handled_by' => $request->user()->id, 'updated_at' => now()]);
        }
        if (min($data['design_eff'], $data['operating_eff']) <= 1) {
            $this->controlFailure($control, $data['note'] ?? null, $request->user());
            return $this->ok('Hasil pengujian dicatat. Kontrol Tidak Efektif: improvement plan dibuka dan risiko terkait ditandai untuk ditinjau.');
        }
        return $this->ok('Hasil pengujian kontrol dicatat.');
    }

    /** Kegagalan kontrol → improvement plan + peringatan ke pemilik kontrol/Risk Manager + peninjauan residual risiko terkait. */
    private function controlFailure(Control $control, ?string $note, $user): void
    {
        $alerts = app(\App\Services\AlertService::class);
        $risks = $control->risks()->with('owner')->get();
        $alerts->openImprovement($control, 'control_failure', "Perbaikan kontrol tidak efektif: {$control->name}", $note, $control->owner_id, $risks->first(), $control->unit_id);
        $managers = \App\Models\User::where('role', 'risk_manager')->where('active', true)->get()->all();
        $alerts->raise('control_failure', 'warning', $control, "Kontrol {$control->code} tidak efektif", $note ?: $control->name,
            route('controls.show', $control, false), "ctlfail:{$control->id}:" . now()->format('Y-m-d'), array_filter([$control->owner, ...$managers]));
        foreach ($risks as $risk) {
            $alerts->raise('control_failure', 'warning', $risk, "Kontrol {$control->code} tidak efektif — tinjau residual risiko {$risk->code}", $control->name,
                route('risks.show', $risk, false), "ctlfail:{$control->id}:{$risk->id}:" . now()->format('Y-m-d'), array_filter([$risk->owner]));
        }
    }

    private function nextTest(string $frequency, string $from): string
    {
        $d = \Carbon\Carbon::parse($from);
        $next = match ($frequency) {
            'daily' => $d->addDay(), 'weekly' => $d->addWeek(), 'monthly' => $d->addMonth(), 'quarterly' => $d->addMonths(3), 'semester' => $d->addMonths(6), default => $d->addYear(),
        };
        return $next->toDateString();
    }

    private function rules(Request $request): array
    {
        $org = $request->user()->organization_id;
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'objective' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:4000'],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->where('organization_id', $org)],
            'unit_id' => ['nullable', Rule::exists('org_units', 'id')->where('organization_id', $org)],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly', 'quarterly', 'semester', 'annual', 'event'])],
            'type' => ['required', Rule::in(['preventive', 'detective', 'corrective'])],
            'mode' => ['required', Rule::in(['manual', 'automated'])],
            'design_eff' => ['nullable', 'integer', 'between:1,4'],
            'operating_eff' => ['nullable', 'integer', 'between:1,4'],
            'active' => ['nullable', 'boolean'],
            'risk_ids' => ['nullable', 'array'],
            'risk_ids.*' => ['integer', Rule::exists('risks', 'id')->where('organization_id', $org)->where(fn ($q) => ($ids = $request->user()->accessibleUnitIds()) !== null ? $q->whereIn('unit_id', $ids) : $q)],
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\LossEvent;
use App\Models\RiskCategory;
use App\Services\AlertService;
use App\Support\Numbering;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Pelaporan insiden & basis data kerugian (§4.14, §4.15). */
class IncidentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Incident::class);
        $f = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in([...array_keys(config('manrisk.incident_statuses')), 'open'])], 'year' => ['nullable', 'integer'],
            'risk_id' => ['nullable', 'integer'], 'unit_id' => ['nullable', 'integer']]);
        $q = $this->scopeUnits(Incident::query())->with(['risk:id,code,name', 'unit:id,name', 'reporter:id,name']);
        if (!empty($f['q'])) {
            $q->where(fn ($w) => $w->where('code', 'like', "%{$f['q']}%")->orWhere('title', 'like', "%{$f['q']}%"));
        }
        if (!empty($f['status'])) {
            $f['status'] === 'open' ? $q->where('status', '!=', 'closed') : $q->where('status', $f['status']);
        }
        if (!empty($f['year'])) {
            $q->whereYear('occurred_at', $f['year']);
        }
        if (!empty($f['risk_id'])) {
            $q->where('risk_id', $f['risk_id']);
        }
        if (!empty($f['unit_id'])) {
            $unit = \App\Models\OrgUnit::find($f['unit_id']);
            $q->whereIn('unit_id', $unit ? $unit->descendantIds() : [0]); // termasuk sub-unit
        }
        $incidents = $q->orderByDesc('occurred_at')->paginate(20)->withQueryString();
        $all = $this->scopeUnits(Incident::query())->get(['id', 'status', 'loss_amount', 'occurred_at']);
        return Inertia::render('Incidents/Index', [
            'incidents' => $incidents,
            'filters' => $f,
            'filter_risk' => !empty($f['risk_id']) ? \App\Models\Risk::find($f['risk_id'])?->only('id', 'code', 'name') : null,
            'stats' => ['total' => $all->count(), 'open' => $all->where('status', '!=', 'closed')->count(), 'loss_ytd' => (float) $all->filter(fn ($i) => $i->occurred_at->year === now()->year)->sum('loss_amount'), 'by_status' => $all->countBy('status')],
            'risks' => $this->riskOptions(),
            'units' => $this->unitOptions(),
            'can' => ['write' => $request->user()->can('create', Incident::class), 'delete' => $request->user()->can('delete', new Incident())],
        ]);
    }

    public function show(Incident $incident)
    {
        $this->authorize('view', $incident);
        $u = auth()->user();
        $incident->load(['risk:id,code,name,unit_id,organization_id', 'unit:id,name', 'reporter:id,name', 'lossEvents.category:id,name', 'documents.uploader:id,name', 'lessons.creator:id,name',
            'improvements' => fn ($q) => $q->with('pic:id,name')->orderByRaw("case status when 'done' then 1 else 0 end")->latest()]);
        $risks = collect($this->riskOptions());
        if ($incident->risk && !$risks->contains('id', $incident->risk_id)) {
            $risks->prepend($incident->risk); // risiko tertaut tetap terpilih walau sudah ditutup
        }
        return Inertia::render('Incidents/Show', ['incident' => $incident, 'categories' => RiskCategory::orderBy('sort')->get(['id', 'name']),
            'risks' => $risks->map(fn ($r) => ['id' => $r->id, 'name' => "{$r->code} · {$r->name}"])->values(), 'units' => $this->unitOptions(true),
            'can' => ['update' => $u->can('update', $incident), 'create_risk' => $u->can('create', \App\Models\Risk::class), 'upload' => $u->can('create', \App\Models\Document::class)]]);
    }

    public function store(Request $request, AlertService $alerts)
    {
        $this->authorize('create', Incident::class);
        $data = $this->rules($request);
        $risk = !empty($data['risk_id']) ? \App\Models\Risk::find($data['risk_id']) : null;
        $data['unit_id'] = ($data['unit_id'] ?? null) ?: ($risk?->unit_id ?? $request->user()->unit_id); // unit default: unit risiko, lalu unit pelapor
        $incident = Incident::create($data + ['code' => Numbering::next(Incident::class, 'INC'), 'reported_by' => $request->user()->id]);
        $this->syncLoss($incident);
        $alerts->checkIncident($incident);
        return redirect()->route('incidents.show', $incident)->with('success', "Insiden {$incident->code} dilaporkan.");
    }

    public function update(Request $request, Incident $incident, AlertService $alerts)
    {
        $this->authorize('update', $incident);
        $data = $this->rules($request, $incident);
        $wasClosed = $incident->status === 'closed';
        $incident->update($data + ['closed_at' => $data['status'] === 'closed' ? ($incident->closed_at ?? now()) : null]);
        if ($incident->wasChanged(['loss_amount', 'risk_id', 'title', 'occurred_at', 'impact'])) {
            $this->syncLoss($incident->unsetRelation('risk'));
        }
        if ($incident->status === 'closed' && !$wasClosed) {
            $alerts->resolve($incident, ['incident']); // insiden ditutup → peringatan selesai
        }
        return $this->ok('Insiden diperbarui.');
    }

    /** Loss database mengikuti kerugian insiden (dibuat/diperbarui; dihapus bila kerugian menjadi nol). */
    private function syncLoss(Incident $incident): void
    {
        if ((float) $incident->loss_amount <= 0) {
            if ($incident->wasChanged('loss_amount')) {
                $incident->lossEvents()->delete();
            }
            return;
        }
        $risk = $incident->risk;
        $loss = LossEvent::firstOrNew(['incident_id' => $incident->id]);
        $loss->organization_id = $incident->organization_id;
        $loss->fill(['category_id' => $risk?->category_id ?? $loss->category_id, 'year' => $incident->occurred_at->year, 'risk_name' => $risk?->name ?? $incident->title, 'event' => $incident->title,
            'amount' => $incident->loss_amount, 'description' => $incident->impact])->save();
    }

    public function destroy(Incident $incident)
    {
        $this->authorize('delete', $incident);
        $incident->delete();
        return redirect()->route('incidents.index')->with('success', 'Insiden dihapus.');
    }

    public function lesson(Request $request, Incident $incident)
    {
        $this->authorize('update', $incident);
        $data = $request->validate(['text' => ['required', 'string', 'max:2000']]);
        $incident->lessons()->create(['organization_id' => $incident->organization_id, 'text' => $data['text'], 'created_by' => $request->user()->id]);
        return $this->ok('Lesson learned disimpan.');
    }

    private function rules(Request $request, ?Incident $current = null): array
    {
        $org = $request->user()->organization_id;
        $ids = $request->user()->accessibleUnitIds();
        // pengguna bercakupan unit: risiko/unit harus dalam cakupan (tautan yang sudah ada tetap boleh dipertahankan)
        $scoped = fn ($col, $keep) => fn ($q) => $ids !== null ? $q->where(fn ($w) => $w->whereIn($col, $ids)->when($keep, fn ($x) => $x->orWhere('id', $keep))) : $q;
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
            'location' => ['nullable', 'string', 'max:160'],
            'risk_id' => ['nullable', Rule::exists('risks', 'id')->where('organization_id', $org)->where($scoped('unit_id', $current?->risk_id))],
            'unit_id' => ['nullable', Rule::exists('org_units', 'id')->where('organization_id', $org)->where($scoped('id', $current?->unit_id))],
            'chronology' => ['nullable', 'string', 'max:4000'],
            'cause' => ['nullable', 'string', 'max:2000'],
            'impact' => ['nullable', 'string', 'max:2000'],
            'loss_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'loss_type' => ['nullable', 'string', 'max:40'],
            'response' => ['nullable', 'string', 'max:4000'],
            'corrective_action' => ['nullable', 'string', 'max:4000'],
            'status' => ['required', Rule::in(array_keys(config('manrisk.incident_statuses')))],
        ]);
    }

    // ---- Loss database ----
    public function losses(Request $request)
    {
        $this->authorize('viewAny', LossEvent::class);
        $ids = $request->user()->accessibleUnitIds();
        // pengguna bercakupan unit hanya melihat kerugian dari insiden unitnya
        $losses = LossEvent::with(['category:id,name', 'incident:id,code,risk_id', 'incident.risk:id,code'])->when($ids !== null, fn ($q) => $q->whereHas('incident', fn ($i) => $i->whereIn('unit_id', $ids)))
            ->orderByDesc('year')->orderByDesc('amount')->get();
        return Inertia::render('Incidents/Losses', [
            'losses' => $losses,
            'by_year' => $losses->groupBy('year')->map(fn ($g, $y) => ['year' => $y, 'total' => (float) $g->sum('amount'), 'n' => $g->count()])->sortBy('year')->values(),
            'by_category' => $losses->groupBy(fn ($l) => $l->category?->name ?? 'Lainnya')->map(fn ($g, $c) => ['name' => $c, 'total' => (float) $g->sum('amount'), 'n' => $g->count()])->sortByDesc('total')->values(),
            'categories' => RiskCategory::orderBy('sort')->get(['id', 'name']),
            'incidents' => $this->scopeUnits(Incident::query())->orderByDesc('occurred_at')->get(['id', 'code', 'title']),
            'can' => ['write' => $request->user()->can('create', LossEvent::class), 'delete' => $request->user()->can('delete', new LossEvent())],
        ]);
    }

    public function storeLoss(Request $request)
    {
        $this->authorize('create', LossEvent::class);
        LossEvent::create($this->lossRules($request));
        return $this->ok('Data kerugian ditambahkan.');
    }

    public function updateLoss(Request $request, LossEvent $loss)
    {
        $this->authorize('update', $loss);
        $loss->update($this->lossRules($request));
        return $this->ok('Data kerugian diperbarui.');
    }

    public function destroyLoss(LossEvent $loss)
    {
        $this->authorize('delete', $loss);
        $loss->delete();
        return $this->ok('Data kerugian dihapus.');
    }

    private function lossRules(Request $request): array
    {
        $org = $request->user()->organization_id;
        return $request->validate([
            'incident_id' => ['nullable', Rule::exists('incidents', 'id')->where('organization_id', $org)],
            'category_id' => ['nullable', Rule::exists('risk_categories', 'id')->where('organization_id', $org)],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'risk_name' => ['required', 'string', 'max:255'],
            'event' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}

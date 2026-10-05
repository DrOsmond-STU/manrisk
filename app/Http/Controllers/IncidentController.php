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
        $f = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(array_keys(config('manrisk.incident_statuses')))], 'year' => ['nullable', 'integer']]);
        $q = $this->scopeUnits(Incident::query())->with(['risk:id,code,name', 'unit:id,name', 'reporter:id,name']);
        if (!empty($f['q'])) {
            $q->where(fn ($w) => $w->where('code', 'like', "%{$f['q']}%")->orWhere('title', 'like', "%{$f['q']}%"));
        }
        if (!empty($f['status'])) {
            $q->where('status', $f['status']);
        }
        if (!empty($f['year'])) {
            $q->whereYear('occurred_at', $f['year']);
        }
        $incidents = $q->orderByDesc('occurred_at')->paginate(20)->withQueryString();
        $all = $this->scopeUnits(Incident::query())->get(['id', 'status', 'loss_amount', 'occurred_at']);
        return Inertia::render('Incidents/Index', [
            'incidents' => $incidents,
            'filters' => $f,
            'stats' => ['total' => $all->count(), 'open' => $all->where('status', '!=', 'closed')->count(), 'loss_ytd' => (float) $all->filter(fn ($i) => $i->occurred_at->year === now()->year)->sum('loss_amount'), 'by_status' => $all->countBy('status')],
            'risks' => $this->riskOptions(),
            'units' => $this->unitOptions(),
            'can' => ['write' => $request->user()->can('create', Incident::class), 'delete' => $request->user()->can('delete', new Incident())],
        ]);
    }

    public function show(Incident $incident)
    {
        $this->authorize('view', $incident);
        $incident->load(['risk:id,code,name', 'unit:id,name', 'reporter:id,name', 'lossEvents.category:id,name', 'documents.uploader:id,name', 'lessons.creator:id,name']);
        return Inertia::render('Incidents/Show', ['incident' => $incident, 'categories' => RiskCategory::orderBy('sort')->get(['id', 'name']), 'can' => ['update' => auth()->user()->can('update', $incident)]]);
    }

    public function store(Request $request, AlertService $alerts)
    {
        $this->authorize('create', Incident::class);
        $data = $this->rules($request);
        $incident = Incident::create($data + ['code' => Numbering::next(Incident::class, 'INC'), 'reported_by' => $request->user()->id]);
        if ($incident->loss_amount > 0) {
            LossEvent::create(['organization_id' => $incident->organization_id, 'incident_id' => $incident->id, 'category_id' => $incident->risk?->category_id, 'year' => $incident->occurred_at->year,
                'risk_name' => $incident->risk?->name ?? $incident->title, 'event' => $incident->title, 'amount' => $incident->loss_amount, 'description' => $incident->impact]);
        }
        $alerts->checkIncident($incident);
        return redirect()->route('incidents.show', $incident)->with('success', "Insiden {$incident->code} dilaporkan.");
    }

    public function update(Request $request, Incident $incident)
    {
        $this->authorize('update', $incident);
        $data = $this->rules($request);
        $incident->update($data + ['closed_at' => $data['status'] === 'closed' ? ($incident->closed_at ?? now()) : null]);
        return $this->ok('Insiden diperbarui.');
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

    private function rules(Request $request): array
    {
        $org = $request->user()->organization_id;
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
            'location' => ['nullable', 'string', 'max:160'],
            'risk_id' => ['nullable', Rule::exists('risks', 'id')->where('organization_id', $org)],
            'unit_id' => ['nullable', Rule::exists('org_units', 'id')->where('organization_id', $org)],
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
        $losses = LossEvent::with(['category:id,name', 'incident:id,code'])->orderByDesc('year')->orderByDesc('amount')->get();
        return Inertia::render('Incidents/Losses', [
            'losses' => $losses,
            'by_year' => $losses->groupBy('year')->map(fn ($g, $y) => ['year' => $y, 'total' => (float) $g->sum('amount'), 'n' => $g->count()])->sortBy('year')->values(),
            'by_category' => $losses->groupBy(fn ($l) => $l->category?->name ?? 'Lainnya')->map(fn ($g, $c) => ['name' => $c, 'total' => (float) $g->sum('amount'), 'n' => $g->count()])->sortByDesc('total')->values(),
            'categories' => RiskCategory::orderBy('sort')->get(['id', 'name']),
            'incidents' => Incident::orderByDesc('occurred_at')->get(['id', 'code', 'title']),
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

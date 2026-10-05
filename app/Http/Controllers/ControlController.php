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
        $f = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'type' => ['nullable', Rule::in(['preventive', 'detective', 'corrective'])], 'eff' => ['nullable', Rule::in(['weak', 'ok'])]]);
        $q = Control::with(['owner:id,name', 'unit:id,name', 'risks:id,code,name,residual_level'])->withCount('assessments');
        if (!empty($f['q'])) {
            $q->where(fn ($w) => $w->where('code', 'like', "%{$f['q']}%")->orWhere('name', 'like', "%{$f['q']}%"));
        }
        if (!empty($f['type'])) {
            $q->where('type', $f['type']);
        }
        if (($f['eff'] ?? null) === 'weak') {
            $q->where(fn ($w) => $w->where('operating_eff', '<=', 2)->orWhere('design_eff', '<=', 2));
        }
        $controls = $q->orderBy('code')->get();
        $all = Control::where('active', true)->get(['id', 'design_eff', 'operating_eff', 'type', 'next_test_at']);
        return Inertia::render('Controls/Index', [
            'controls' => $controls->map(fn ($c) => $c->toArray() + ['overall' => $c->overallEffectiveness()]),
            'filters' => $f,
            'stats' => [
                'total' => $all->count(),
                'effective' => $all->filter(fn ($c) => ($c->overallEffectiveness() ?? 0) >= 3)->count(),
                'weak' => $all->filter(fn ($c) => $c->overallEffectiveness() !== null && $c->overallEffectiveness() <= 2)->count(),
                'untested' => $all->whereNull('operating_eff')->count(),
                'due' => $all->filter(fn ($c) => $c->next_test_at && $c->next_test_at->isPast())->count(),
                'by_type' => $all->countBy('type'),
            ],
            'users' => $this->userOptions(),
            'units' => $this->unitOptions(),
            'risks' => $this->riskOptions(),
            'can' => ['write' => $request->user()->can('create', Control::class), 'delete' => $request->user()->can('delete', new Control())],
        ]);
    }

    public function show(Control $control)
    {
        $this->authorize('view', $control);
        $control->load(['owner:id,name', 'unit:id,name', 'risks:id,code,name,residual_score,residual_level', 'assessments' => fn ($q) => $q->with('tester:id,name')->orderByDesc('tested_at'), 'documents.uploader:id,name']);
        return Inertia::render('Controls/Show', ['control' => $control->toArray() + ['overall' => $control->overallEffectiveness()], 'can' => ['write' => auth()->user()->can('update', $control)]]);
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
            $control->risks()->sync($data['risk_ids'] ?? []);
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
        return $this->ok('Hasil pengujian kontrol dicatat.');
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
            'risk_ids.*' => ['integer', Rule::exists('risks', 'id')->where('organization_id', $org)],
        ]);
    }
}

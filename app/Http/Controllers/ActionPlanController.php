<?php

namespace App\Http\Controllers;

use App\Models\ActionPlan;
use App\Models\ActionProgress;
use App\Models\Risk;
use App\Support\Numbering;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Action plan mitigasi: CRUD, progres, pembatalan, kanban (§4.9). */
class ActionPlanController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', ActionPlan::class);
        $f = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['todo', 'running', 'done', 'overdue', 'cancelled'])], 'pic_id' => ['nullable', 'integer'], 'risk_id' => ['nullable', 'integer']]);
        $q = $this->scopeUnits(ActionPlan::query())->with(['risk:id,code,name,residual_level,unit_id', 'pic:id,name', 'unit:id,name']);
        if (!empty($f['q'])) {
            $q->where(fn ($w) => $w->where('code', 'like', "%{$f['q']}%")->orWhere('title', 'like', "%{$f['q']}%"));
        }
        foreach (['pic_id', 'risk_id'] as $k) {
            if (!empty($f[$k])) {
                $q->where($k, $f[$k]);
            }
        }
        $plans = $q->orderBy('due_date')->get()->map(fn ($p) => $p->toArray() + ['status' => $p->computedStatus(), 'can_progress' => $request->user()->can('progress', $p), 'can_update' => $request->user()->can('update', $p)]);
        if (!empty($f['status'])) {
            $plans = $plans->where('status', $f['status'])->values();
        }
        return Inertia::render('ActionPlans/Index', [
            'plans' => $plans,
            'filters' => $f,
            'stats' => $plans->countBy('status'),
            'risks' => $this->riskOptions(),
            'users' => $this->userOptions(),
            'units' => $this->unitOptions(),
            'can' => ['write' => $request->user()->can('create', ActionPlan::class), 'delete' => $request->user()->can('delete', new ActionPlan())],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', ActionPlan::class);
        $data = $this->rules($request);
        $risk = Risk::findOrFail($data['risk_id']);
        abort_unless($request->user()->can('update', $risk), 403);
        $plan = ActionPlan::create($data + ['code' => Numbering::next(ActionPlan::class, 'AP'), 'unit_id' => $data['unit_id'] ?? $risk->unit_id, 'created_by' => $request->user()->id, 'progress' => 0]);
        if ($risk->status === 'monitoring') {
            $risk->update(['status' => 'treating']);
        }
        return $this->ok("Action plan {$plan->code} ditambahkan.");
    }

    public function update(Request $request, ActionPlan $plan)
    {
        $this->authorize('update', $plan);
        $data = $this->rules($request);
        $plan->update($data);
        return $this->ok('Action plan diperbarui.');
    }

    public function destroy(ActionPlan $plan)
    {
        $this->authorize('delete', $plan);
        $plan->delete();
        return $this->ok('Action plan dihapus.');
    }

    public function progress(Request $request, ActionPlan $plan)
    {
        $this->authorize('progress', $plan);
        if ($plan->cancelled_at) {
            return back()->with('error', 'Action plan sudah dibatalkan.');
        }
        $data = $request->validate(['progress' => ['required', 'integer', 'between:0,100'], 'note' => ['nullable', 'string', 'max:2000', Rule::requiredIf(fn () => (int) $request->progress === 100)]]);
        ActionProgress::create(['action_plan_id' => $plan->id, 'user_id' => $request->user()->id, 'from_pct' => $plan->progress, 'to_pct' => $data['progress'], 'note' => $data['note'] ?? null]);
        $plan->update(['progress' => $data['progress'], 'completed_at' => $data['progress'] >= 100 ? now() : null]);
        if ($data['progress'] >= 100) {
            $risk = $plan->risk;
            if ($risk && $risk->status === 'treating' && !$risk->actionPlans()->whereNull('cancelled_at')->where('progress', '<', 100)->exists()) {
                $risk->update(['status' => 'monitoring']);
            }
        }
        return $this->ok('Progres dicatat.');
    }

    public function cancel(Request $request, ActionPlan $plan)
    {
        $this->authorize('update', $plan);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $plan->update(['cancelled_at' => now(), 'cancel_reason' => $data['reason']]);
        return $this->ok('Action plan dibatalkan.');
    }

    public function show(ActionPlan $plan)
    {
        $this->authorize('view', $plan);
        $plan->load(['risk:id,code,name', 'pic:id,name', 'unit:id,name', 'progressLog.user:id,name', 'documents.uploader:id,name']);
        return Inertia::render('ActionPlans/Show', ['plan' => $plan->toArray() + ['status' => $plan->computedStatus()], 'can' => ['update' => auth()->user()->can('update', $plan), 'progress' => auth()->user()->can('progress', $plan)]]);
    }

    private function rules(Request $request): array
    {
        $org = $request->user()->organization_id;
        return $request->validate([
            'risk_id' => ['required', Rule::exists('risks', 'id')->where('organization_id', $org)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'pic_id' => ['nullable', Rule::exists('users', 'id')->where('organization_id', $org)],
            'unit_id' => ['nullable', Rule::exists('org_units', 'id')->where('organization_id', $org)],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'priority' => ['required', Rule::in(array_keys(config('manrisk.priorities')))],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:start_date'],
            'expected_dl' => ['nullable', 'integer', 'between:0,4'],
            'expected_di' => ['nullable', 'integer', 'between:0,4'],
        ]);
    }
}

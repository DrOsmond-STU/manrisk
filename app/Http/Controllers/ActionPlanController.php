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
        $f = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['todo', 'running', 'done', 'verify', 'overdue', 'cancelled'])], 'pic_id' => ['nullable', 'integer'], 'risk_id' => ['nullable', 'integer'], 'mine' => ['nullable', 'boolean'], 'unit_id' => ['nullable', 'integer']]);
        $me = $request->user();
        $q = ActionPlan::query()->when($me->accessibleUnitIds() !== null, fn ($x) => $x->where(fn ($w) => $w->whereIn('unit_id', $me->accessibleUnitIds())->orWhere('pic_id', $me->id)))->with(['risk:id,code,name,residual_level,unit_id', 'pic:id,name', 'unit:id,name']);
        if (!empty($f['q'])) {
            $q->where(fn ($w) => $w->where('code', 'like', "%{$f['q']}%")->orWhere('title', 'like', "%{$f['q']}%"));
        }
        if (!empty($f['mine'])) {
            $q->where('pic_id', $request->user()->id);
        }
        foreach (['pic_id', 'risk_id', 'unit_id'] as $k) {
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
        $needEvidence = (int) $request->progress === 100 && config('manrisk.plan_completion_verification') && !$plan->documents()->exists();
        $data = $request->validate([
            'progress' => ['required', 'integer', 'between:0,100'],
            'note' => ['nullable', 'string', 'max:2000', Rule::requiredIf(fn () => (int) $request->progress === 100)],
            'evidence' => [...\App\Support\DocumentStore::rules($needEvidence)],
        ], ['evidence.required' => 'Penyelesaian 100% wajib melampirkan bukti pelaksanaan.']);
        if ($request->hasFile('evidence')) {
            \App\Support\DocumentStore::store($request->file('evidence'), $request->user(), $plan, ['type' => 'evidence', 'title' => "Bukti {$plan->code} ({$data['progress']}%)", 'version' => 1, 'status' => 'review'], 'evidence');
        }
        ActionProgress::create(['action_plan_id' => $plan->id, 'user_id' => $request->user()->id, 'from_pct' => $plan->progress, 'to_pct' => $data['progress'], 'note' => $data['note'] ?? null]);
        $selfVerify = $plan->risk && $request->user()->id === $plan->risk->owner_id;
        $plan->update(['progress' => $data['progress'], 'completed_at' => $data['progress'] >= 100 ? now() : null,
            'verified_at' => $data['progress'] >= 100 && (!config('manrisk.plan_completion_verification') || $selfVerify) ? now() : null,
            'verified_by' => $data['progress'] >= 100 && (!config('manrisk.plan_completion_verification') || $selfVerify) ? $request->user()->id : null]);
        if ($data['progress'] >= 100 && !$plan->verified_at && $plan->risk) {
            app(\App\Services\AlertService::class)->raise('plan_verify', 'info', $plan, "Action plan {$plan->code} selesai 100% — mohon verifikasi", $plan->title,
                route('action-plans.show', $plan, false), "plan:{$plan->id}:verify:" . now()->format('YmdHi'), array_filter([$plan->risk->owner]));
        }
        if ($data['progress'] >= 100 && $plan->verified_at) {
            $risk = $plan->risk;
            if ($risk && $risk->status === 'treating' && !$risk->actionPlans()->whereNull('cancelled_at')->where(fn ($q) => $q->where('progress', '<', 100)->orWhereNull('verified_at'))->exists()
                && $risk->residual_score <= (int) ($risk->category?->appetite ?? 6)) {
                $risk->update(['status' => 'monitoring']);
            }
        }
        return $this->ok('Progres dicatat.');
    }

    /** Verifikasi penyelesaian oleh Risk Owner/Risk Manager (F-TRT-07). */
    public function verify(Request $request, ActionPlan $plan)
    {
        $user = $request->user();
        abort_unless($user->can('update', $plan) && ($user->id === $plan->risk?->owner_id || $user->hasRole('super_admin', 'risk_admin', 'risk_manager')), 403);
        abort_unless($plan->progress >= 100 && !$plan->verified_at, 422, 'Action plan belum selesai atau sudah diverifikasi.');
        $data = $request->validate(['action' => ['required', Rule::in(['approve', 'reject'])], 'note' => ['nullable', 'string', 'max:1000', 'required_if:action,reject']]);
        if ($data['action'] === 'reject') {
            ActionProgress::create(['action_plan_id' => $plan->id, 'user_id' => $user->id, 'from_pct' => 100, 'to_pct' => 90, 'note' => 'Verifikasi ditolak: ' . $data['note']]);
            $plan->update(['progress' => 90, 'completed_at' => null]);
            return $this->ok('Penyelesaian dikembalikan ke PIC.');
        }
        $plan->update(['verified_at' => now(), 'verified_by' => $user->id]);
        $risk = $plan->risk;
        if ($risk && $risk->status === 'treating' && !$risk->actionPlans()->whereNull('cancelled_at')->where(fn ($q) => $q->where('progress', '<', 100)->orWhereNull('verified_at'))->exists()
            && $risk->residual_score <= (int) ($risk->category?->appetite ?? 6)) {
            $risk->update(['status' => 'monitoring']);
        }
        return $this->ok("Penyelesaian {$plan->code} diverifikasi.");
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
        $u = auth()->user();
        $plan->load('verifier:id,name');
        return Inertia::render('ActionPlans/Show', ['plan' => $plan->toArray() + ['status' => $plan->computedStatus()], 'can' => ['update' => $u->can('update', $plan), 'progress' => $u->can('progress', $plan),
            'verify' => $plan->progress >= 100 && !$plan->verified_at && $u->can('update', $plan) && ($u->id === $plan->risk?->owner_id || $u->hasRole('super_admin', 'risk_admin', 'risk_manager'))]]);
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

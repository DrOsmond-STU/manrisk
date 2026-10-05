<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Risk;
use App\Models\RiskSnapshot;
use App\Services\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Review & evaluasi berkala (§4.16). */
class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Review::class);
        $f = $request->validate(['period' => ['nullable', 'string', 'max:20'], 'risk_id' => ['nullable', 'integer']]);
        $q = Review::with(['risk:id,code,name,unit_id,residual_score,residual_level', 'reviewer:id,name']);
        if ($request->user()->isUnitScoped()) {
            $q->whereIn('risk_id', \App\Support\UnitScope::riskIdsQuery($request->user()));
        }
        if (!empty($f['period'])) {
            $q->where('period', $f['period']);
        }
        if (!empty($f['risk_id'])) {
            $q->where('risk_id', $f['risk_id']);
        }
        $reviews = $q->latest()->paginate(25)->withQueryString();
        $periodNow = now()->format('Y') . '-Q' . now()->quarter;
        $reviewedIds = Review::where('period', $periodNow)->pluck('risk_id');
        $periods = Review::select('period')->distinct()->orderByDesc('period')->pluck('period');
        return Inertia::render('Reviews/Index', [
            'reviews' => $reviews,
            'filters' => $f,
            'periods' => $periods,
            'period_now' => $periodNow,
            'due' => $this->scopeUnits(Risk::query())->where('status', '!=', 'closed')->whereNotIn('id', $reviewedIds)->with('unit:id,name')->orderByDesc('residual_score')->get(['id', 'code', 'name', 'unit_id', 'residual_score', 'residual_level', 'previous_score']),
            'risks' => $this->riskOptions(),
            'can' => ['write' => $request->user()->can('create', Review::class)],
        ]);
    }

    public function store(Request $request, ApprovalService $approvals)
    {
        $this->authorize('create', Review::class);
        $data = $request->validate([
            'risk_id' => ['required', Rule::exists('risks', 'id')->where('organization_id', $request->user()->organization_id)],
            'period_type' => ['required', Rule::in(['monthly', 'quarterly', 'semester', 'annual', 'adhoc'])],
            'period' => ['required', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:4000'],
            'decision' => ['required', Rule::in(['continue', 'change_treatment', 'close', 'escalate'])],
        ]);
        $risk = Risk::findOrFail($data['risk_id']);
        abort_unless($request->user()->can('view', $risk), 403);
        $prev = $risk->snapshots()->orderByDesc('period')->skip(1)->value('residual_score') ?? $risk->previous_score;
        $review = Review::create($data + [
            'previous_score' => $prev, 'current_score' => $risk->residual_score,
            'trend' => $prev === null ? 'flat' : ($risk->residual_score > $prev ? 'up' : ($risk->residual_score < $prev ? 'down' : 'flat')),
            'reviewer_id' => $request->user()->id, 'signed_at' => now(),
        ]);
        if ($data['decision'] === 'close' && $risk->status !== 'closed' && $risk->status !== 'pending') {
            $approvals->submit($risk, 'closure', $request->user(), 'Hasil review ' . $data['period'] . ': ' . ($data['note'] ?? 'direkomendasikan ditutup'));
        }
        if ($data['decision'] === 'escalate') {
            app(\App\Services\AlertService::class)->raise('review_escalate', 'warning', $risk, "Review {$data['period']}: risiko {$risk->code} dieskalasi", $data['note'] ?? null, route('risks.show', $risk, false), "review:{$review->id}", \App\Models\User::where('role', 'management')->where('active', true)->get()->all());
        }
        return $this->ok('Review dicatat.');
    }

    public function destroy(Review $review)
    {
        $this->authorize('delete', $review);
        $review->delete();
        return $this->ok('Review dihapus.');
    }

    /** Buat snapshot bulanan manual (biasanya lewat scheduler). */
    public function snapshot(Request $request)
    {
        abort_unless($request->user()->hasRole('super_admin', 'risk_admin', 'risk_manager'), 403);
        $period = now()->format('Y-m');
        $n = 0;
        foreach (Risk::all() as $r) {
            RiskSnapshot::updateOrCreate(['risk_id' => $r->id, 'period' => $period], ['organization_id' => $r->organization_id, 'inherent_score' => $r->inherent_score, 'residual_score' => $r->residual_score, 'level' => $r->residual_level, 'status' => $r->status, 'created_at' => now()]);
            $n++;
        }
        return $this->ok("Snapshot {$period} dibuat untuk {$n} risiko.");
    }
}

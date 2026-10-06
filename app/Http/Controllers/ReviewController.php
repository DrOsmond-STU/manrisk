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
        // Dianggap sudah direview bila ada review (jenis apa pun) yang ditandatangani dalam triwulan berjalan
        $reviewedIds = Review::whereBetween('signed_at', [now()->startOfQuarter(), now()->endOfQuarter()])->pluck('risk_id');
        $periods = Review::select('period')->distinct()->orderByDesc('period')->pluck('period');
        return Inertia::render('Reviews/Index', [
            'reviews' => $reviews,
            'filters' => $f,
            'focus_risk' => !empty($f['risk_id']) ? Risk::find($f['risk_id'])?->only('id', 'code', 'name') : null,
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
            'reviewer_id' => $request->user()->id, 'signed_at' => now(), 'signed_ip' => $request->ip(),
        ]);
        $warning = null;
        if ($data['decision'] === 'close' && $risk->status !== 'closed' && $risk->status !== 'pending') {
            // Sama dengan pengajuan penutupan dari halaman risiko: action plan harus selesai/dibatalkan dulu
            $openPlans = $risk->actionPlans()->whereNull('cancelled_at')->where('progress', '<', 100)->count();
            if ($openPlans > 0) {
                $warning = "Review dicatat, tetapi penutupan belum diajukan: masih ada {$openPlans} action plan yang belum selesai.";
            } else {
                $approvals->submit($risk, 'closure', $request->user(), 'Hasil review ' . $data['period'] . ': ' . ($data['note'] ?? 'direkomendasikan ditutup'));
            }
        }
        if ($data['decision'] === 'escalate') {
            app(\App\Services\AlertService::class)->raise('review_escalate', 'warning', $risk, "Review {$data['period']}: risiko {$risk->code} dieskalasi", $data['note'] ?? null, route('risks.show', $risk, false), "review:{$review->id}", \App\Models\User::where('role', 'management')->where('active', true)->get()->all());
        }
        return $warning ? back()->with('warning', $warning) : $this->ok('Review dicatat.');
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

    /** Berita acara reviu per periode (PDF) dengan daftar penandatangan (F-REV-04). */
    public function minutes(Request $request)
    {
        $this->authorize('viewAny', Review::class);
        $period = $request->validate(['period' => ['required', 'string', 'max:20']])['period'];
        $q = Review::with(['risk:id,code,name,unit_id,residual_level', 'risk.unit:id,name', 'reviewer:id,name,position'])->where('period', $period)->orderBy('created_at');
        if ($request->user()->isUnitScoped()) {
            $q->whereIn('risk_id', \App\Support\UnitScope::riskIdsQuery($request->user()));
        }
        $reviews = $q->get();
        abort_if($reviews->isEmpty(), 404, 'Belum ada reviu pada periode ini.');
        $signers = $reviews->groupBy('reviewer_id')->map(fn ($g) => ['name' => $g->first()->reviewer?->name, 'position' => $g->first()->reviewer?->position, 'at' => $g->max('signed_at'), 'ip' => $g->sortByDesc('signed_at')->first()->signed_ip, 'n' => $g->count()])->values();
        \App\Models\AuditLog::record('exported', $request->user(), ['minutes' => [null, $period]], 'reviews.minutes');
        return \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.review_minutes', ['title' => "Berita Acara Reviu Risiko {$period}", 'org' => $request->user()->organization?->name, 'generated_at' => now(), 'by' => $request->user()->name, 'params' => [],
            'period' => $period, 'reviews' => $reviews, 'signers' => $signers])->download("berita-acara-reviu-{$period}.pdf");
    }
}

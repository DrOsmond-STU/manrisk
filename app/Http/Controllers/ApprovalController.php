<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Services\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ApprovalController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Approval::class);
        $user = $request->user();
        $all = Approval::with(['steps.approver:id,name', 'requester:id,name', 'subject'])->latest()->get();
        $mine = $all->filter(fn ($a) => $a->status === 'pending' && $a->requester_id !== $user->id && ($s = $a->steps->firstWhere('step_no', $a->current_step)) && ($s->role === $user->role || $user->role === 'super_admin'));
        $map = fn ($a) => [
            'id' => $a->id, 'code' => $a->code, 'type' => $a->type, 'type_label' => ApprovalService::TYPES[$a->type] ?? $a->type, 'status' => $a->status,
            'current_step' => $a->current_step, 'total_steps' => $a->total_steps, 'note' => $a->note, 'created_at' => $a->created_at, 'decided_at' => $a->decided_at,
            'requester' => $a->requester?->name, 'subject' => $this->subjectInfo($a),
            'steps' => $a->steps->sortBy('step_no')->values()->map(fn ($s) => ['step_no' => $s->step_no, 'role' => \App\Models\User::ROLES[$s->role] ?? $s->role, 'action' => $s->action, 'note' => $s->note, 'acted_at' => $s->acted_at, 'due_at' => $s->due_at, 'approver' => $s->approver?->name]),
            'can_decide' => $user->can('decide', $a),
        ];
        return Inertia::render('Approvals/Index', [
            'inbox' => $mine->values()->map($map),
            'requested' => $all->where('requester_id', $user->id)->values()->map($map),
            'history' => $all->whereIn('status', ['approved', 'rejected', 'revision'])->take(100)->values()->map($map),
            'pending_all' => $user->hasRole('super_admin', 'risk_admin', 'risk_manager', 'management', 'auditor') ? $all->where('status', 'pending')->values()->map($map) : [],
        ]);
    }

    public function decide(Request $request, Approval $approval, ApprovalService $service)
    {
        $this->authorize('decide', $approval);
        $data = $request->validate(['action' => ['required', Rule::in(['approve', 'revise', 'reject'])], 'note' => ['nullable', 'string', 'max:1000']]);
        $service->decide($approval, $request->user(), $data['action'], $data['note'] ?? null);
        $msg = ['approve' => 'disetujui', 'revise' => 'dikembalikan untuk revisi', 'reject' => 'ditolak'][$data['action']];
        return back()->with('success', "Pengajuan {$approval->code} {$msg}.");
    }

    private function subjectInfo(Approval $a): ?array
    {
        $s = $a->subject;
        if (!$s) {
            return null;
        }
        return ['type' => class_basename($s), 'id' => $s->id, 'code' => $s->code ?? null, 'name' => $s->name ?? $s->title ?? null,
            'score' => $s->residual_score ?? null, 'level' => $s->residual_level ?? null, 'url' => $s instanceof \App\Models\Risk ? route('risks.show', $s, false) : null];
    }
}

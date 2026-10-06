<?php

namespace App\Services;

use App\Models\Approval;
use App\Models\ApprovalStep;
use App\Models\AuditLog;
use App\Models\Risk;
use App\Models\User;
use App\Support\Numbering;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Alur persetujuan berjenjang (spesifikasi §6.4).
 * Tahap bawaan: risk_owner → risk_manager → management. Tahap yang perannya sama dengan
 * pengaju dilewati; pengaju tidak pernah dapat menyetujui pengajuannya sendiri.
 */
class ApprovalService
{
    public const TYPES = [
        'new_risk' => 'Risiko Baru',
        'score_change' => 'Perubahan Skor',
        'treatment' => 'Rencana Mitigasi',
        'retain' => 'Penerimaan Risiko',
        'closure' => 'Penutupan Risiko',
        'criteria' => 'Perubahan Kriteria',
    ];

    public const CHAIN = ['risk_owner', 'risk_manager', 'management'];

    /** Peran yang boleh memutus pada tahap tertentu (super_admin dapat menggantikan siapa pun). */
    public static function canActAs(User $user, string $role): bool
    {
        return $user->role === $role || $user->role === 'super_admin';
    }

    public function submit(Model $subject, string $type, User $requester, ?string $note = null, array $payload = []): Approval
    {
        return DB::transaction(function () use ($subject, $type, $requester, $note, $payload) {
            $existing = Approval::where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey())
                ->where('status', 'pending')->first();
            if ($existing) {
                throw ValidationException::withMessages(['approval' => 'Masih ada pengajuan yang belum selesai untuk objek ini.']);
            }

            if ($subject instanceof Risk) {
                $payload['prev_status'] ??= $subject->status; // dipulihkan bila pengajuan ditolak/dikembalikan
            }
            $chain = $this->chainFor($subject, $type, $requester);
            $approval = Approval::create([
                'organization_id' => $requester->organization_id,
                'code' => Numbering::next(Approval::class, 'WF', $requester->organization_id),
                'type' => $type,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'requester_id' => $requester->id,
                'current_step' => 1,
                'total_steps' => count($chain),
                'status' => 'pending',
                'note' => $note,
                'payload' => $payload ?: null,
            ]);
            $sla = (int) config('manrisk.approval_sla_days', 3);
            foreach ($chain as $i => $role) {
                ApprovalStep::create(['approval_id' => $approval->id, 'step_no' => $i + 1, 'role' => $role, 'due_at' => $i === 0 ? now()->addWeekdays($sla) : null]);
            }
            if ($subject instanceof Risk) {
                $subject->forceFill(['status' => 'pending', 'submitted_at' => now()])->save();
            }
            AuditLog::record('submitted', $subject, [], 'approval:' . $approval->code);
            $this->notifyStep($approval, $chain[0], $subject);
            return $approval;
        });
    }

    /** Rantai tahap: skor residual ≥ ambang eskalasi wajib sampai Management; peran pengaju dilewati. */
    public function chainFor(Model $subject, string $type, User $requester): array
    {
        $chain = self::CHAIN;
        $escalate = (int) (\App\Models\CriteriaVersion::current()?->thresholds['escalate'] ?? 16);
        if ($subject instanceof Risk && $subject->residual_score < $escalate && in_array($type, ['new_risk', 'score_change', 'treatment'], true)) {
            $chain = ['risk_owner', 'risk_manager'];
        }
        if ($type === 'criteria') {
            $chain = ['risk_manager', 'management'];
        }
        $chain = array_values(array_filter($chain, fn ($r) => $r !== $requester->role));
        return $chain ?: ['risk_manager'];
    }

    public function decide(Approval $approval, User $actor, string $action, ?string $note = null): Approval
    {
        if (!in_array($action, ['approve', 'revise', 'reject'], true)) {
            throw ValidationException::withMessages(['action' => 'Aksi tidak dikenal.']);
        }
        if ($approval->status !== 'pending') {
            throw ValidationException::withMessages(['approval' => 'Pengajuan ini sudah diputus.']);
        }
        if ($approval->requester_id === $actor->id) {
            throw ValidationException::withMessages(['approval' => 'Pengaju tidak dapat memutus pengajuannya sendiri.']);
        }
        $step = $approval->steps()->where('step_no', $approval->current_step)->firstOrFail();
        if (!self::canActAs($actor, $step->role)) {
            throw ValidationException::withMessages(['approval' => 'Tahap ini menunggu keputusan ' . (User::ROLES[$step->role] ?? $step->role) . '.']);
        }
        if (in_array($action, ['revise', 'reject'], true) && trim((string) $note) === '') {
            throw ValidationException::withMessages(['note' => 'Catatan wajib diisi untuk revisi atau penolakan.']);
        }

        return DB::transaction(function () use ($approval, $actor, $action, $note, $step) {
            $step->update(['approver_id' => $actor->id, 'action' => $action, 'note' => $note, 'acted_at' => now()]);
            app(AlertService::class)->resolve($approval, ['approval_request', 'approval_due']); // tahap ini sudah diputus
            $subject = $approval->subject;

            if ($action === 'approve') {
                if ($approval->current_step < $approval->total_steps) {
                    $approval->current_step++;
                    $approval->steps()->where('step_no', $approval->current_step)->update(['due_at' => now()->addWeekdays((int) config('manrisk.approval_sla_days', 3))]);
                    $this->notifyStep($approval, $approval->steps()->where('step_no', $approval->current_step)->value('role'), $subject);
                } else {
                    $approval->status = 'approved';
                    $approval->decided_at = now();
                    $this->applyApproved($approval, $subject, $actor);
                }
            } else {
                $approval->status = $action === 'revise' ? 'revision' : 'rejected';
                $approval->decided_at = now();
                $this->revert($approval, $subject, $action, $note);
            }
            $approval->save();
            if ($subject) {
                AuditLog::record('approval_' . $action, $subject, ['step' => [$step->step_no, $note]], 'approval:' . $approval->code);
            }
            if ($approval->status !== 'pending') {
                // Keputusan akhir diberitahukan kepada pengaju
                $label = ['approved' => 'disetujui', 'revision' => 'dikembalikan untuk revisi', 'rejected' => 'ditolak'][$approval->status];
                app(AlertService::class)->raise('approval_result', $approval->status === 'approved' ? 'info' : 'warning', $approval,
                    "Pengajuan {$approval->code} {$label}" . ($subject ? " — {$subject->code}" : ''), $note,
                    $subject instanceof Risk ? route('risks.show', $subject, false) : route('approvals.index', ['id' => $approval->id], false),
                    "approval:{$approval->id}:result", array_filter([$approval->requester]));
            }
            return $approval->fresh(['steps']);
        });
    }

    /** Beri tahu pemegang peran tahap berjalan bahwa ada pengajuan menunggu keputusannya. */
    private function notifyStep(Approval $approval, ?string $role, ?Model $subject): void
    {
        if (!$role) {
            return;
        }
        $users = User::with('unit')->where('role', $role)->where('active', true)->where('id', '!=', $approval->requester_id)->get()
            ->filter(fn ($u) => !$subject instanceof Risk || $u->canAccessUnit($subject->unit_id))->all();
        app(AlertService::class)->raise('approval_request', 'info', $approval,
            "Menunggu keputusan " . (User::ROLES[$role] ?? $role) . ": {$approval->code} · " . (self::TYPES[$approval->type] ?? $approval->type) . ($subject ? " {$subject->code}" : ''), $approval->note,
            route('approvals.index', ['id' => $approval->id], false), "approval:{$approval->id}:step:{$approval->current_step}", $users);
    }

    /**
     * Pengajuan ditolak/dikembalikan: risiko baru/penerimaan kembali ke draft (belum pernah disetujui);
     * perubahan skor dikembalikan ke nilai terakhir yang disetujui; penutupan kembali ke status semula.
     */
    private function revert(Approval $approval, ?Model $subject, string $action, ?string $note): void
    {
        if (!$subject instanceof Risk) {
            return;
        }
        $payload = $approval->payload ?? [];
        if ($approval->type === 'score_change' && !empty($payload['restore'])) {
            $subject->forceFill($payload['restore']);
            app(\App\Support\Scoring::class)->apply($subject, $subject->category);
            $subject->versions()->where('version', $subject->version)->whereNull('approved_at')->get()
                ->each(fn ($v) => $v->update(['note' => trim(($v->note ? $v->note . ' — ' : '') . ($action === 'revise' ? 'Dikembalikan' : 'Ditolak') . ": {$note}")]));
        }
        if (in_array($approval->type, ['new_risk', 'retain'], true)) {
            $status = 'draft';
        } else {
            $status = $payload['prev_status'] ?? null;
            if (!$status || in_array($status, ['pending', 'draft'], true)) {
                $status = $subject->residual_score > (int) ($subject->category?->appetite ?? 6) ? 'treating' : 'monitoring';
            }
        }
        $subject->forceFill(['status' => $status])->save();
    }

    private function applyApproved(Approval $approval, ?Model $subject, User $actor): void
    {
        if (!$subject instanceof Risk) {
            return;
        }
        $appetite = (int) ($subject->category?->appetite ?? 6);
        switch ($approval->type) {
            case 'closure':
                $subject->forceFill(['status' => 'closed', 'closed_at' => now(), 'closed_reason' => $approval->note])->save();
                break;
            case 'retain':
                $subject->forceFill(['status' => 'monitoring', 'treatment' => 'retain', 'approved_at' => now()])->save();
                break;
            default:
                $subject->forceFill([
                    'status' => $subject->residual_score > $appetite ? 'treating' : 'monitoring',
                    'approved_at' => now(),
                ])->save();
                $subject->versions()->where('version', $subject->version)->update(['approved_at' => now(), 'approved_by' => $actor->id]);
        }
    }
}

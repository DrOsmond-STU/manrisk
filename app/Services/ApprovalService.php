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
            return $approval;
        });
    }

    /** Rantai tahap: skor residual ≥ ambang eskalasi wajib sampai Management; peran pengaju dilewati. */
    public function chainFor(Model $subject, string $type, User $requester): array
    {
        $chain = self::CHAIN;
        if ($subject instanceof Risk && $subject->residual_score < 16 && in_array($type, ['new_risk', 'score_change', 'treatment'], true)) {
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
            $subject = $approval->subject;

            if ($action === 'approve') {
                if ($approval->current_step < $approval->total_steps) {
                    $approval->current_step++;
                    $approval->steps()->where('step_no', $approval->current_step)->update(['due_at' => now()->addWeekdays((int) config('manrisk.approval_sla_days', 3))]);
                } else {
                    $approval->status = 'approved';
                    $approval->decided_at = now();
                    $this->applyApproved($approval, $subject, $actor);
                }
            } elseif ($action === 'revise') {
                $approval->status = 'revision';
                $approval->decided_at = now();
                if ($subject instanceof Risk) {
                    $subject->forceFill(['status' => 'draft'])->save();
                }
            } else {
                $approval->status = 'rejected';
                $approval->decided_at = now();
                if ($subject instanceof Risk) {
                    $subject->forceFill(['status' => $approval->type === 'new_risk' ? 'draft' : ($subject->residual_score > ($subject->category?->appetite ?? 6) ? 'treating' : 'monitoring')])->save();
                }
            }
            $approval->save();
            if ($subject) {
                AuditLog::record('approval_' . $action, $subject, ['step' => [$step->step_no, $note]], 'approval:' . $approval->code);
            }
            return $approval->fresh(['steps']);
        });
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

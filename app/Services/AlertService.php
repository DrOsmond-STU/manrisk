<?php

namespace App\Services;

use App\Models\ActionPlan;
use App\Models\Alert;
use App\Models\Approval;
use App\Models\Control;
use App\Models\Document;
use App\Models\Kri;
use App\Models\Risk;
use App\Notifications\AlertNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

/**
 * Early warning (spesifikasi §12): membuat peringatan di dashboard dan mengirim notifikasi
 * email kepada penerima yang relevan. dedupe_key mencegah peringatan yang sama berulang.
 */
class AlertService
{
    public function raise(string $type, string $severity, ?Model $subject, string $title, ?string $message = null, ?string $link = null, ?string $dedupeKey = null, array $recipients = []): ?Alert
    {
        $orgId = $subject->organization_id ?? auth()->user()?->organization_id;
        if ($dedupeKey && Alert::withoutGlobalScopes()->where('organization_id', $orgId)->where('dedupe_key', $dedupeKey)->exists()) {
            return null;
        }
        $alert = Alert::withoutGlobalScopes()->create([
            'organization_id' => $orgId,
            'type' => $type,
            'severity' => $severity,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'title' => mb_substr($title, 0, 255),
            'message' => $message,
            'link' => $link,
            'dedupe_key' => $dedupeKey,
        ]);
        $recipients = collect($recipients)->filter()->unique('id');
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new AlertNotification($alert));
        }
        return $alert;
    }

    /** Dipanggil setelah nilai KRI baru disimpan. */
    public function checkKri(Kri $kri, string $previousStatus): void
    {
        $status = $kri->status;
        if ($status === $previousStatus || $status === 'normal') {
            return;
        }
        $risk = $kri->risk;
        $label = $status === 'critical' ? 'KRITIS' : 'PERINGATAN';
        if ($status === 'critical' && !\App\Models\Improvement::withoutGlobalScopes()->where('organization_id', $kri->organization_id)->where('source_type', 'kri_breach')->where('source_ref', $kri->code)->where('status', '!=', 'done')->exists()) {
            \App\Models\Improvement::withoutGlobalScopes()->create(['organization_id' => $kri->organization_id, 'code' => \App\Support\Numbering::next(\App\Models\Improvement::class, 'IMP', $kri->organization_id),
                'source_type' => 'kri_breach', 'source_ref' => $kri->code, 'title' => "Tindak lanjut pelanggaran KRI {$kri->name}", 'description' => "Nilai {$kri->last_value} {$kri->unit} melewati ambang kritis {$kri->threshold_crit}.",
                'pic_id' => $kri->owner_id, 'unit_id' => $risk?->unit_id, 'due_date' => now()->addDays(30), 'status' => 'open']);
        }
        if ($status === 'critical' && $risk && $risk->status === 'monitoring') {
            $risk->forceFill(['status' => 'treating'])->save(); // Dipantau → Dalam Penanganan saat KRI kritis (§6.1)
        }
        $this->raise(
            'kri_breach',
            $status === 'critical' ? 'critical' : 'warning',
            $kri,
            "$label · KRI {$kri->name} {$kri->last_value} {$kri->unit} " . ($status === 'critical' ? 'melewati batas toleransi' : 'memasuki zona waspada'),
            $risk ? "Risiko terkait: {$risk->code} {$risk->name}" : null,
            route('kris.index', [], false),
            "kri:{$kri->id}:{$status}:" . now()->format('Y-m'),
            array_filter([$kri->owner, $risk?->owner, ...$this->managers($kri->organization_id)])
        );
    }

    /** Dipanggil setelah skor residual berubah. */
    public function checkScoreChange(Risk $risk, int $previousScore): void
    {
        if ($risk->residual_score <= $previousScore) {
            return;
        }
        $from = app(\App\Support\Scoring::class)->level(...$this->lvFromScore($previousScore));
        if (\App\Support\Scoring::LEVEL_ORDER[$risk->residual_level] <= \App\Support\Scoring::LEVEL_ORDER[$from]) {
            return;
        }
        $this->raise(
            'score_up',
            $risk->residual_level === 'very_high' ? 'critical' : 'warning',
            $risk,
            "PERINGATAN · Risiko “{$risk->name}” meningkat dari " . \App\Support\Scoring::levelLabel($from) . ' menjadi ' . \App\Support\Scoring::levelLabel($risk->residual_level),
            "Skor residual {$previousScore} → {$risk->residual_score}",
            route('risks.show', $risk, false),
            "risk:{$risk->id}:score:{$risk->version}",
            array_filter([$risk->owner, ...$this->managers($risk->organization_id)])
        );
    }

    public function checkIncident(\App\Models\Incident $incident): void
    {
        $risk = $incident->risk;
        $this->raise('incident', 'critical', $incident, "INSIDEN · {$incident->title}", $risk ? "Risiko terkait: {$risk->code} {$risk->name}" : null,
            route('incidents.show', $incident, false), "incident:{$incident->id}", array_filter([$risk?->owner, ...$this->managers($incident->organization_id)]));
    }

    /** Dijalankan terjadwal setiap hari (spesifikasi §4.7, §4.8, §4.13). */
    public function dailySweep(int $organizationId): array
    {
        $count = 0;
        $today = now()->startOfDay();
        $plans = ActionPlan::withoutGlobalScopes()->where('organization_id', $organizationId)->whereNull('cancelled_at')->where('progress', '<', 100)->with(['pic', 'risk.owner'])->get();
        foreach ($plans as $p) {
            $days = (int) round($today->diffInDays($p->due_date, false));
            $recipients = array_filter([$p->pic, $p->risk?->owner]);
            if ($days < 0) {
                $late = -$days;
                $sev = $late >= config('manrisk.action_escalation_days') ? 'critical' : 'warning';
                if ($sev === 'critical') {
                    $recipients = [...$recipients, ...$this->managers($organizationId)];
                }
                $count += (int) (bool) $this->raise('action_overdue', $sev, $p, "Action plan {$p->code} melewati tenggat {$late} hari", $p->title,
                    route('action-plans.index', [], false), "action:{$p->id}:overdue:" . ($sev === 'critical' ? 'esc' : 'late'), $recipients);
            } elseif (in_array($days, config('manrisk.action_reminder_days'), true)) {
                $count += (int) (bool) $this->raise('action_due', 'info', $p, "Action plan {$p->code} jatuh tempo dalam {$days} hari", $p->title,
                    route('action-plans.index', [], false), "action:{$p->id}:due:{$days}", $recipients);
            }
        }
        foreach (Document::withoutGlobalScopes()->where('organization_id', $organizationId)->whereNotNull('expires_at')->with('uploader')->get() as $d) {
            $days = (int) round($today->diffInDays($d->expires_at, false));
            if (in_array($days, config('manrisk.document_expiry_days'), true)) {
                $count += (int) (bool) $this->raise('doc_expiring', 'info', $d, "Dokumen “{$d->title}” kedaluwarsa dalam {$days} hari", null,
                    route('documents.index', [], false), "doc:{$d->id}:exp:{$days}", array_filter([$d->uploader]));
            } elseif ($days < 0 && $d->status !== 'expired') {
                $d->update(['status' => 'expired']);
            }
        }
        foreach (Control::withoutGlobalScopes()->where('organization_id', $organizationId)->where('active', true)->whereNotNull('next_test_at')->where('next_test_at', '<', $today)->with('owner')->get() as $c) {
            $count += (int) (bool) $this->raise('control_due', 'warning', $c, "Kontrol {$c->code} belum diuji sesuai jadwal", $c->name,
                route('controls.index', [], false), "control:{$c->id}:due:" . $c->next_test_at->format('Y-m'), array_filter([$c->owner]));
        }
        foreach (Approval::withoutGlobalScopes()->where('organization_id', $organizationId)->where('status', 'pending')->with('steps')->get() as $a) {
            $step = $a->steps->firstWhere('step_no', $a->current_step);
            if ($step && $step->due_at && $step->due_at->isPast()) {
                $count += (int) (bool) $this->raise('approval_due', 'warning', $a, "Pengajuan {$a->code} melewati SLA tahap " . (User::ROLES[$step->role] ?? $step->role), $a->note,
                    route('approvals.index', [], false), "approval:{$a->id}:sla:{$a->current_step}", User::withoutGlobalScopes()->where('organization_id', $organizationId)->where('role', $step->role)->where('active', true)->get()->all());
            }
        }
        return ['alerts' => $count];
    }

    private function managers(int $organizationId): array
    {
        return User::withoutGlobalScopes()->where('organization_id', $organizationId)->where('role', 'risk_manager')->where('active', true)->get()->all();
    }

    private function lvFromScore(int $score): array
    {
        // pendekatan: cari pasangan L×I yang menghasilkan skor (untuk pelabelan level lama)
        for ($l = 5; $l >= 1; $l--) {
            if ($score % $l === 0 && $score / $l <= 5) {
                return [$l, (int) ($score / $l)];
            }
        }
        return [1, min(5, $score)];
    }
}

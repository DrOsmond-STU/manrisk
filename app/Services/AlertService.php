<?php

namespace App\Services;

use App\Models\ActionPlan;
use App\Models\Alert;
use App\Models\Approval;
use App\Models\Control;
use App\Models\Document;
use App\Models\Improvement;
use App\Models\Kri;
use App\Models\Risk;
use App\Models\Scopes\OrganizationScope;
use App\Support\Numbering;
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

    /**
     * Tandai selesai peringatan aktif milik subjek (mis. KRI kembali normal, action plan selesai,
     * insiden ditutup, kontrol sudah diuji) agar daftar peringatan mencerminkan kondisi terkini.
     */
    public function resolve(Model $subject, array $types = []): int
    {
        return Alert::withoutGlobalScopes()->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey())
            ->whereNull('handled_at')->when($types, fn ($q) => $q->whereIn('type', $types))
            ->update(['handled_at' => now(), 'handled_by' => auth()->id(), 'updated_at' => now()]);
    }

    /**
     * Buka improvement untuk sumber tertentu bila belum ada yang masih terbuka (tanpa duplikat).
     * Sumber (kontrol/KRI/insiden/review) dan risiko terkait disimpan agar dapat ditelusuri dari kedua arah.
     */
    public function openImprovement(Model $subject, string $sourceType, string $title, ?string $description, ?int $picId, ?Risk $risk, ?int $unitId = null): Improvement
    {
        $orgId = $subject->organization_id;
        $existing = Improvement::withoutGlobalScope(OrganizationScope::class)->where('organization_id', $orgId)->where('source_type', $sourceType)
            ->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey())->where('status', '!=', 'done')->first();
        if ($existing) {
            return $existing;
        }
        $imp = new Improvement(['code' => Numbering::next(Improvement::class, 'IMP', $orgId), 'source_type' => $sourceType, 'source_ref' => $subject->code ?? null,
            'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(), 'risk_id' => $risk?->id,
            'title' => mb_substr($title, 0, 255), 'description' => $description, 'pic_id' => $picId, 'unit_id' => $unitId ?? $risk?->unit_id,
            'due_date' => now()->addDays(30), 'status' => 'open']);
        $imp->organization_id = $orgId;
        $imp->save();
        return $imp;
    }

    /** Tautan peringatan KRI: daftar KRI milik risiko terkait (atau KRI kritis bila tanpa risiko). */
    public static function kriLink(Kri $kri): string
    {
        return $kri->risk_id ? route('kris.index', ['risk_id' => $kri->risk_id], false) : route('kris.index', ['status' => $kri->status === 'normal' ? null : $kri->status], false);
    }

    /** Dipanggil setelah nilai KRI baru disimpan atau ambangnya diubah. */
    public function checkKri(Kri $kri, string $previousStatus): void
    {
        $status = $kri->status;
        if ($status === 'normal' && $previousStatus !== 'normal') {
            $this->resolve($kri, ['kri_breach', 'kri_due']); // kembali normal → peringatan lama selesai
        }
        if ($status === $previousStatus || $status === 'normal') {
            return;
        }
        $risk = $kri->risk;
        $label = $status === 'critical' ? 'KRITIS' : 'PERINGATAN';
        if ($status === 'critical') {
            $this->openImprovement($kri, 'kri_breach', "Tindak lanjut pelanggaran KRI {$kri->name}", "Nilai {$kri->last_value} {$kri->unit} melewati ambang kritis {$kri->threshold_crit}.", $kri->owner_id, $risk);
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
            self::kriLink($kri),
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
        // withoutGlobalScope(OrganizationScope) — bukan withoutGlobalScopes() — agar data terhapus (soft delete) tetap tersaring
        $plans = ActionPlan::withoutGlobalScope(OrganizationScope::class)->where('organization_id', $organizationId)->whereNull('cancelled_at')->where('progress', '<', 100)
            ->whereHas('risk', fn ($r) => $r->withoutGlobalScope(OrganizationScope::class)->where('status', '!=', 'closed'))->with(['pic', 'risk.owner'])->get();
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
                    route('action-plans.show', $p, false), "action:{$p->id}:overdue:" . ($sev === 'critical' ? 'esc' : 'late'), $recipients);
            } elseif (in_array($days, config('manrisk.action_reminder_days'), true)) {
                $count += (int) (bool) $this->raise('action_due', 'info', $p, "Action plan {$p->code} jatuh tempo dalam {$days} hari", $p->title,
                    route('action-plans.show', $p, false), "action:{$p->id}:due:{$days}", $recipients);
            }
        }
        foreach (Document::withoutGlobalScope(OrganizationScope::class)->where('organization_id', $organizationId)->whereNotNull('expires_at')->with('uploader')->get() as $d) {
            $days = (int) round($today->diffInDays($d->expires_at, false));
            if (in_array($days, config('manrisk.document_expiry_days'), true)) {
                $count += (int) (bool) $this->raise('doc_expiring', 'info', $d, "Dokumen “{$d->title}” kedaluwarsa dalam {$days} hari", null,
                    route('documents.index', ['history' => $d->id], false), "doc:{$d->id}:exp:{$days}", array_filter([$d->uploader]));
            } elseif ($days < 0 && $d->status !== 'expired') {
                $d->update(['status' => 'expired']);
            }
        }
        foreach (Control::withoutGlobalScope(OrganizationScope::class)->where('organization_id', $organizationId)->where('active', true)->whereNotNull('next_test_at')->where('next_test_at', '<', $today)->with('owner')->get() as $c) {
            $count += (int) (bool) $this->raise('control_due', 'warning', $c, "Kontrol {$c->code} belum diuji sesuai jadwal", $c->name,
                route('controls.show', $c, false), "control:{$c->id}:due:" . $c->next_test_at->format('Y-m'), array_filter([$c->owner]));
        }
        // KRI yang belum diperbarui sesuai frekuensinya (§4.8)
        $grace = ['daily' => 2, 'weekly' => 10, 'monthly' => 40, 'quarterly' => 100];
        foreach (Kri::withoutGlobalScope(OrganizationScope::class)->where('organization_id', $organizationId)->where('active', true)->with(['owner', 'values' => fn ($v) => $v->latest('period')->limit(1)])->get() as $k) {
            $last = $k->values->first()?->period ?? $k->created_at;
            $limit = $grace[$k->frequency] ?? 40;
            if ($last && $last->copy()->startOfDay()->diffInDays($today) > $limit) {
                $count += (int) (bool) $this->raise('kri_due', 'info', $k, "Nilai KRI {$k->code} belum diperbarui ({$k->frequency})", $k->name,
                    self::kriLink($k), "kri:{$k->id}:due:" . $today->format('Y-m'), array_filter([$k->owner]));
            }
        }
        foreach (Approval::withoutGlobalScopes()->where('organization_id', $organizationId)->where('status', 'pending')->with('steps')->get() as $a) {
            $step = $a->steps->firstWhere('step_no', $a->current_step);
            if ($step && $step->due_at && $step->due_at->isPast()) {
                $count += (int) (bool) $this->raise('approval_due', 'warning', $a, "Pengajuan {$a->code} melewati SLA tahap " . (User::ROLES[$step->role] ?? $step->role), $a->note,
                    route('approvals.index', ['id' => $a->id], false), "approval:{$a->id}:sla:{$a->current_step}", User::withoutGlobalScopes()->where('organization_id', $organizationId)->where('role', $step->role)->where('active', true)->get()->all());
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

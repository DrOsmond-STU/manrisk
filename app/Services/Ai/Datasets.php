<?php

namespace App\Services\Ai;

use App\Models\ActionPlan;
use App\Models\Approval;
use App\Models\Control;
use App\Models\Improvement;
use App\Models\Incident;
use App\Models\Kri;
use App\Models\LossEvent;
use App\Models\OrgUnit;
use App\Models\Review;
use App\Models\Risk;
use App\Models\RiskCategory;
use App\Models\RiskSnapshot;
use App\Models\User;
use App\Support\Scoring;
use App\Support\UnitScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Sumber data untuk AI Assistant dan AI Analitik. Setiap dataset selalu dibatasi ke
 * organisasi (global scope) dan cakupan unit pengguna, sama seperti halaman modulnya,
 * sehingga asisten tidak pernah melihat atau menampilkan data di luar hak akses pengguna.
 *
 * Definisi: dims (pengelompokan/filter, menghasilkan label), measures (angka), dates (tanggal),
 * list (kolom daftar) dan link (halaman objek). Semua kunci adalah daftar putih: spesifikasi
 * dari AI yang menyebut kunci lain ditolak oleh AnalyticsEngine.
 */
class Datasets
{
    private array $units;
    private array $users;
    private array $categories;
    private array $themes;

    public function __construct(private User $user)
    {
        $this->units = OrgUnit::pluck('name', 'id')->all();
        $this->users = User::pluck('name', 'id')->all();
        $cats = RiskCategory::get(['id', 'name', 'parent_id'])->keyBy('id');
        $this->categories = $cats->map->name->all();
        // T1 (tema) = leluhur teratas kategori
        $this->themes = $cats->map(function ($c) use ($cats) {
            $n = 0;
            while ($c->parent_id && isset($cats[$c->parent_id]) && $n++ < 10) {
                $c = $cats[$c->parent_id];
            }
            return $c->name;
        })->all();
    }

    public function user(): User
    {
        return $this->user;
    }

    // ---------- query bercakupan (sama dengan halaman modul) ----------

    public function risks(): Builder
    {
        $ids = $this->user->accessibleUnitIds();
        return Risk::query()->when($ids !== null, fn ($q) => $q->whereIn('unit_id', $ids));
    }

    public function actionPlans(): Builder
    {
        $u = $this->user;
        $ids = $u->accessibleUnitIds();
        return ActionPlan::query()->when($ids !== null, fn ($q) => $q->where(fn ($w) => $w->whereIn('unit_id', $ids)->orWhereIn('risk_id', UnitScope::riskIdsQuery($u))->orWhere('pic_id', $u->id)));
    }

    public function kris(): Builder
    {
        $ids = $this->user->accessibleUnitIds();
        return Kri::query()->when($ids !== null, fn ($q) => $q->where(fn ($w) => $w->whereNull('risk_id')->orWhereIn('risk_id', UnitScope::riskIdsQuery($this->user))));
    }

    public function incidents(): Builder
    {
        $ids = $this->user->accessibleUnitIds();
        return Incident::query()->when($ids !== null, fn ($q) => $q->whereIn('unit_id', $ids));
    }

    public function controls(): Builder
    {
        $ids = $this->user->accessibleUnitIds();
        return Control::query()->when($ids !== null, fn ($q) => $q->where(fn ($w) => $w->whereNull('unit_id')->orWhereIn('unit_id', $ids)->orWhereHas('risks', fn ($r) => $r->whereIn('unit_id', $ids))));
    }

    public function losses(): Builder
    {
        $ids = $this->user->accessibleUnitIds();
        return LossEvent::query()->when($ids !== null, fn ($q) => $q->whereHas('incident', fn ($i) => $i->whereIn('unit_id', $ids)));
    }

    public function improvements(): Builder
    {
        $u = $this->user;
        $ids = $u->accessibleUnitIds();
        return Improvement::query()->when($ids !== null, fn ($q) => $q->where(fn ($w) => $w->whereIn('unit_id', $ids)->orWhereIn('risk_id', UnitScope::riskIdsQuery($u))
            ->orWhere('pic_id', $u->id)->orWhere(fn ($x) => $x->whereNull('unit_id')->whereNull('risk_id'))));
    }

    public function reviews(): Builder
    {
        return Review::query()->when($this->user->isUnitScoped(), fn ($q) => $q->whereIn('risk_id', UnitScope::riskIdsQuery($this->user)));
    }

    public function approvals(): Builder
    {
        return UnitScope::morph(Approval::query(), $this->user, false);
    }

    public function snapshots(): Builder
    {
        return RiskSnapshot::query()->when($this->user->isUnitScoped(), fn ($q) => $q->whereIn('risk_id', UnitScope::riskIdsQuery($this->user)));
    }

    // ---------- label ----------

    public function unitName($id): string
    {
        return $this->units[$id] ?? 'Tanpa unit';
    }

    public function userName($id): string
    {
        return $this->users[$id] ?? 'Belum ditetapkan';
    }

    public function categoryName($id): string
    {
        return $this->categories[$id] ?? 'Lainnya';
    }

    public function themeName($id): string
    {
        return $this->themes[$id] ?? 'Lainnya';
    }

    public static function date($v): ?Carbon
    {
        if (!$v) {
            return null;
        }
        return $v instanceof Carbon ? $v : Carbon::parse($v);
    }

    private static function label(array $map, $key, string $fallback = '—'): string
    {
        return $map[$key] ?? ($key === null || $key === '' ? $fallback : (string) $key);
    }

    public const PLAN_STATUSES = ['todo' => 'Belum mulai', 'running' => 'Berjalan', 'overdue' => 'Terlambat', 'verify' => 'Menunggu verifikasi', 'done' => 'Selesai', 'cancelled' => 'Dibatalkan'];
    public const KRI_STATUSES = ['normal' => 'Normal', 'warning' => 'Waspada', 'critical' => 'Kritis'];
    public const IMPROVEMENT_STATUSES = ['open' => 'Terbuka', 'in_progress' => 'Berjalan', 'done' => 'Selesai'];
    public const IMPROVEMENT_SOURCES = ['incident' => 'Insiden', 'audit' => 'Temuan audit', 'control_failure' => 'Kegagalan kontrol', 'kri_breach' => 'Pelanggaran KRI', 'treatment' => 'Treatment', 'trend' => 'Tren risiko', 'lesson' => 'Lesson learned'];
    public const REVIEW_DECISIONS = ['continue' => 'Lanjutkan', 'change_treatment' => 'Ubah treatment', 'close' => 'Tutup', 'escalate' => 'Eskalasi'];
    public const TRENDS = ['up' => 'Naik', 'flat' => 'Tetap', 'down' => 'Turun'];
    public const APPROVAL_STATUSES = ['pending' => 'Menunggu', 'revision' => 'Revisi', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan'];
    public const APPROVAL_TYPES = ['new_risk' => 'Risiko baru', 'score_change' => 'Perubahan skor', 'treatment' => 'Treatment', 'retain' => 'Penerimaan risiko', 'closure' => 'Penutupan', 'criteria' => 'Kriteria'];
    public const CONTROL_TYPES = ['preventive' => 'Preventif', 'detective' => 'Detektif', 'corrective' => 'Korektif'];

    /** Definisi seluruh dataset. */
    public function defs(): array
    {
        $cfg = config('manrisk');
        $lvl = fn ($k) => Scoring::levelLabel((string) $k);
        $scheme = Scoring::currentScheme();
        $inh = fn ($r) => Scoring::levelLabel(Scoring::levelFromScore((int) $r->inherent_score, $scheme));
        $eff = fn ($v) => $v === null ? 'Belum diuji' : self::label($cfg['effectiveness'], $v);
        // urutan tampilan dimensi berjenjang (terparah lebih dulu), bukan menurut jumlah
        $levelOrder = array_reverse(array_values(Scoring::levels()));
        $kriOrder = ['Kritis', 'Waspada', 'Normal'];
        return [
            'risks' => [
                'label' => 'Risiko (Risk Register)',
                'query' => fn () => $this->risks()->select(['id', 'code', 'name', 'unit_id', 'category_id', 'owner_id', 'objective_id', 'source_kind', 'treatment', 'status', 'due_date', 'inherent_score', 'residual_score', 'target_score', 'inherent_exposure', 'residual_exposure', 'target_exposure', 'residual_level', 'evaluation', 'trend', 'created_at', 'approved_at']),
                'exclude_default' => ['status' => 'closed'],
                'dims' => [
                    'unit' => ['Unit kerja', fn ($r) => $this->unitName($r->unit_id), fn ($r) => $r->unit_id],
                    'category' => ['Kategori risiko', fn ($r) => $this->categoryName($r->category_id), fn ($r) => $r->category_id],
                    'theme' => ['Tema risiko (T1)', fn ($r) => $this->themeName($r->category_id)],
                    'level' => ['Level risiko residual', fn ($r) => $lvl($r->residual_level), fn ($r) => $r->residual_level],
                    'inherent_level' => ['Level risiko inheren', $inh],
                    'evaluation' => ['Status evaluasi', fn ($r) => self::label(Scoring::EVALUATIONS, $r->evaluation), fn ($r) => $r->evaluation],
                    'status' => ['Status risiko', fn ($r) => self::label($cfg['risk_statuses'], $r->status), fn ($r) => $r->status],
                    'treatment' => ['Opsi treatment', fn ($r) => self::label($cfg['treatments'], $r->treatment), fn ($r) => $r->treatment],
                    'source_kind' => ['Sumber risiko', fn ($r) => self::label($cfg['source_kinds'], $r->source_kind), fn ($r) => $r->source_kind],
                    'trend' => ['Tren skor', fn ($r) => self::label(self::TRENDS, $r->trend), fn ($r) => $r->trend],
                    'owner' => ['Pemilik risiko', fn ($r) => $this->userName($r->owner_id), fn ($r) => $r->owner_id],
                ],
                'measures' => [
                    'residual_score' => ['Skor residual', fn ($r) => (float) $r->residual_score, 'score'],
                    'inherent_score' => ['Skor inheren', fn ($r) => (float) $r->inherent_score, 'score'],
                    'target_score' => ['Skor target', fn ($r) => (float) $r->target_score, 'score'],
                    'residual_exposure' => ['Eksposur residual (Rp)', fn ($r) => (float) $r->residual_exposure, 'money'],
                    'inherent_exposure' => ['Eksposur inheren (Rp)', fn ($r) => (float) $r->inherent_exposure, 'money'],
                ],
                'dates' => ['created_at' => ['Tanggal dicatat', fn ($r) => self::date($r->created_at)], 'approved_at' => ['Tanggal disetujui', fn ($r) => self::date($r->approved_at)], 'due_date' => ['Tenggat', fn ($r) => self::date($r->due_date)]],
                'default_date' => 'created_at',
                'list' => ['code' => 'Kode', 'name' => 'Risiko', 'dim:unit' => 'Unit', 'dim:level' => 'Level', 'residual_score' => 'Skor residual', 'dim:evaluation' => 'Evaluasi'],
                'link' => fn ($r) => "/risks/{$r->id}",
                'sort' => 'residual_score',
                'order' => ['level' => $levelOrder, 'inherent_level' => $levelOrder],
            ],
            'action_plans' => [
                'label' => 'Action plan (mitigasi)',
                'query' => fn () => $this->actionPlans()->with('risk:id,code,unit_id')->select(['id', 'code', 'title', 'risk_id', 'pic_id', 'unit_id', 'budget', 'actual_cost', 'priority', 'start_date', 'due_date', 'progress', 'completed_at', 'cancelled_at', 'verified_at', 'created_at']),
                'dims' => [
                    'status' => ['Status action plan', fn ($r) => self::PLAN_STATUSES[$r->computedStatus()], fn ($r) => $r->computedStatus()],
                    'priority' => ['Prioritas', fn ($r) => self::label($cfg['priorities'], $r->priority), fn ($r) => $r->priority],
                    'unit' => ['Unit pelaksana', fn ($r) => $this->unitName($r->unit_id ?? $r->risk?->unit_id), fn ($r) => $r->unit_id ?? $r->risk?->unit_id],
                    'risk' => ['Risiko', fn ($r) => $r->risk?->code ?? '—'],
                    'pic' => ['PIC', fn ($r) => $this->userName($r->pic_id), fn ($r) => $r->pic_id],
                ],
                'measures' => [
                    'progress' => ['Progres (%)', fn ($r) => (float) $r->progress, 'percent'],
                    'budget' => ['Anggaran (Rp)', fn ($r) => (float) $r->budget, 'money'],
                    'actual_cost' => ['Realisasi biaya (Rp)', fn ($r) => (float) $r->actual_cost, 'money'],
                ],
                'dates' => ['due_date' => ['Tenggat', fn ($r) => self::date($r->due_date)], 'created_at' => ['Tanggal dibuat', fn ($r) => self::date($r->created_at)], 'completed_at' => ['Tanggal selesai', fn ($r) => self::date($r->completed_at)]],
                'default_date' => 'due_date',
                'list' => ['code' => 'Kode', 'title' => 'Action plan', 'dim:risk' => 'Risiko', 'dim:status' => 'Status', 'progress' => 'Progres (%)', 'date:due_date' => 'Tenggat'],
                'link' => fn ($r) => "/action-plans/{$r->id}",
                'sort' => 'progress',
            ],
            'kris' => [
                'label' => 'Key Risk Indicator (KRI)',
                'query' => fn () => $this->kris()->with('risk:id,code,unit_id')->select(['id', 'code', 'name', 'risk_id', 'owner_id', 'frequency', 'direction', 'threshold_warn', 'threshold_crit', 'last_value', 'status', 'active', 'updated_at']),
                'dims' => [
                    'status' => ['Status KRI', fn ($r) => self::label(self::KRI_STATUSES, $r->status), fn ($r) => $r->status],
                    'risk' => ['Risiko', fn ($r) => $r->risk?->code ?? 'Tanpa risiko'],
                    'unit' => ['Unit kerja', fn ($r) => $this->unitName($r->risk?->unit_id), fn ($r) => $r->risk?->unit_id],
                    'frequency' => ['Frekuensi', fn ($r) => (string) $r->frequency],
                ],
                'measures' => ['last_value' => ['Nilai terakhir', fn ($r) => (float) $r->last_value, 'number']],
                'dates' => ['updated_at' => ['Terakhir diperbarui', fn ($r) => self::date($r->updated_at)]],
                'default_date' => 'updated_at',
                'list' => ['code' => 'Kode', 'name' => 'KRI', 'dim:risk' => 'Risiko', 'last_value' => 'Nilai terakhir', 'dim:status' => 'Status'],
                'link' => fn ($r) => '/kris?q=' . rawurlencode($r->code),
                'sort' => 'last_value',
                'order' => ['status' => $kriOrder],
            ],
            'incidents' => [
                'label' => 'Insiden',
                'query' => fn () => $this->incidents()->with('risk:id,code')->select(['id', 'code', 'title', 'occurred_at', 'risk_id', 'unit_id', 'loss_amount', 'loss_type', 'status', 'created_at']),
                'dims' => [
                    'status' => ['Status insiden', fn ($r) => self::label($cfg['incident_statuses'], $r->status), fn ($r) => $r->status],
                    'unit' => ['Unit kerja', fn ($r) => $this->unitName($r->unit_id), fn ($r) => $r->unit_id],
                    'loss_type' => ['Jenis kerugian', fn ($r) => $r->loss_type ?: 'Tidak dicatat'],
                    'risk' => ['Risiko terkait', fn ($r) => $r->risk?->code ?? 'Tanpa risiko'],
                ],
                'measures' => ['loss_amount' => ['Kerugian (Rp)', fn ($r) => (float) $r->loss_amount, 'money']],
                'dates' => ['occurred_at' => ['Tanggal kejadian', fn ($r) => self::date($r->occurred_at)]],
                'default_date' => 'occurred_at',
                'list' => ['code' => 'Kode', 'title' => 'Insiden', 'date:occurred_at' => 'Tanggal', 'dim:unit' => 'Unit', 'loss_amount' => 'Kerugian (Rp)', 'dim:status' => 'Status'],
                'link' => fn ($r) => "/incidents/{$r->id}",
                'sort' => 'loss_amount',
            ],
            'controls' => [
                'label' => 'Kontrol',
                'query' => fn () => $this->controls()->withCount('risks')->select(['id', 'code', 'name', 'owner_id', 'unit_id', 'frequency', 'type', 'mode', 'design_eff', 'operating_eff', 'last_tested_at', 'next_test_at', 'active']),
                'dims' => [
                    'type' => ['Tipe kontrol', fn ($r) => self::label(self::CONTROL_TYPES, $r->type), fn ($r) => $r->type],
                    'effectiveness' => ['Efektivitas', fn ($r) => $eff($r->overallEffectiveness())],
                    'mode' => ['Mode', fn ($r) => $r->mode === 'automated' ? 'Otomatis' : 'Manual', fn ($r) => $r->mode],
                    'unit' => ['Unit kerja', fn ($r) => $this->unitName($r->unit_id), fn ($r) => $r->unit_id],
                    'frequency' => ['Frekuensi', fn ($r) => (string) $r->frequency],
                    'test_due' => ['Status pengujian', fn ($r) => !$r->next_test_at ? 'Tanpa jadwal' : (self::date($r->next_test_at)->lt(now()->startOfDay()) ? 'Jatuh tempo' : 'Terjadwal')],
                ],
                'measures' => [
                    'design_eff' => ['Efektivitas desain', fn ($r) => (float) $r->design_eff, 'score'],
                    'operating_eff' => ['Efektivitas operasi', fn ($r) => (float) $r->operating_eff, 'score'],
                    'risks_count' => ['Jumlah risiko dikendalikan', fn ($r) => (float) $r->risks_count, 'number'],
                ],
                'dates' => ['last_tested_at' => ['Terakhir diuji', fn ($r) => self::date($r->last_tested_at)], 'next_test_at' => ['Jadwal uji berikutnya', fn ($r) => self::date($r->next_test_at)]],
                'default_date' => 'last_tested_at',
                'list' => ['code' => 'Kode', 'name' => 'Kontrol', 'dim:type' => 'Tipe', 'dim:effectiveness' => 'Efektivitas', 'date:next_test_at' => 'Uji berikutnya'],
                'link' => fn ($r) => "/controls/{$r->id}",
                'sort' => 'risks_count',
            ],
            'losses' => [
                'label' => 'Loss event (kerugian)',
                'query' => fn () => $this->losses()->select(['id', 'incident_id', 'category_id', 'year', 'risk_name', 'event', 'amount', 'created_at']),
                'dims' => [
                    'category' => ['Kategori risiko', fn ($r) => $this->categoryName($r->category_id), fn ($r) => $r->category_id],
                    'year' => ['Tahun', fn ($r) => (string) $r->year, fn ($r) => $r->year],
                ],
                'measures' => ['amount' => ['Nilai kerugian (Rp)', fn ($r) => (float) $r->amount, 'money']],
                'dates' => ['year' => ['Tahun kejadian', fn ($r) => Carbon::create((int) $r->year, 1, 1)]],
                'default_date' => 'year',
                'list' => ['event' => 'Peristiwa', 'risk_name' => 'Risiko', 'dim:category' => 'Kategori', 'dim:year' => 'Tahun', 'amount' => 'Nilai (Rp)'],
                'link' => fn ($r) => '/incidents/losses',
                'sort' => 'amount',
            ],
            'improvements' => [
                'label' => 'Perbaikan berkelanjutan',
                'query' => fn () => $this->improvements()->select(['id', 'code', 'title', 'source_type', 'unit_id', 'pic_id', 'due_date', 'status', 'created_at']),
                'dims' => [
                    'status' => ['Status', fn ($r) => self::label(self::IMPROVEMENT_STATUSES, $r->status), fn ($r) => $r->status],
                    'source' => ['Sumber perbaikan', fn ($r) => self::label(self::IMPROVEMENT_SOURCES, $r->source_type), fn ($r) => $r->source_type],
                    'unit' => ['Unit kerja', fn ($r) => $this->unitName($r->unit_id), fn ($r) => $r->unit_id],
                    'overdue' => ['Ketepatan waktu', fn ($r) => $r->status !== 'done' && $r->due_date && self::date($r->due_date)->lt(now()->startOfDay()) ? 'Terlambat' : 'Sesuai jadwal'],
                ],
                'measures' => [],
                'dates' => ['created_at' => ['Tanggal dibuat', fn ($r) => self::date($r->created_at)], 'due_date' => ['Tenggat', fn ($r) => self::date($r->due_date)]],
                'default_date' => 'created_at',
                'list' => ['code' => 'Kode', 'title' => 'Perbaikan', 'dim:source' => 'Sumber', 'dim:status' => 'Status', 'date:due_date' => 'Tenggat'],
                'link' => fn ($r) => '/improvements?q=' . rawurlencode($r->code),
                'sort' => null,
            ],
            'reviews' => [
                'label' => 'Risk review',
                'query' => fn () => $this->reviews()->with('risk:id,code')->select(['id', 'period_type', 'period', 'risk_id', 'previous_score', 'current_score', 'trend', 'decision', 'created_at']),
                'dims' => [
                    'risk' => ['Risiko', fn ($r) => $r->risk?->code ?? '—'],
                    'decision' => ['Keputusan review', fn ($r) => self::label(self::REVIEW_DECISIONS, $r->decision), fn ($r) => $r->decision],
                    'period' => ['Periode', fn ($r) => (string) $r->period],
                    'trend' => ['Tren', fn ($r) => self::label(self::TRENDS, $r->trend), fn ($r) => $r->trend],
                ],
                'measures' => ['current_score' => ['Skor saat review', fn ($r) => (float) $r->current_score, 'score']],
                'dates' => ['created_at' => ['Tanggal review', fn ($r) => self::date($r->created_at)]],
                'default_date' => 'created_at',
                'list' => ['dim:period' => 'Periode', 'dim:risk' => 'Risiko', 'current_score' => 'Skor', 'dim:trend' => 'Tren', 'dim:decision' => 'Keputusan'],
                'link' => fn ($r) => "/risks/{$r->risk_id}",
                'sort' => 'current_score',
            ],
            'approvals' => [
                'label' => 'Persetujuan',
                'query' => fn () => $this->approvals()->select(['id', 'code', 'type', 'status', 'current_step', 'total_steps', 'subject_type', 'subject_id', 'created_at', 'decided_at']),
                'dims' => [
                    'status' => ['Status persetujuan', fn ($r) => self::label(self::APPROVAL_STATUSES, $r->status), fn ($r) => $r->status],
                    'type' => ['Jenis pengajuan', fn ($r) => self::label(self::APPROVAL_TYPES, $r->type), fn ($r) => $r->type],
                ],
                'measures' => ['days_open' => ['Lama proses (hari)', fn ($r) => (float) self::date($r->created_at)->diffInDays($r->decided_at ? self::date($r->decided_at) : now()), 'number']],
                'dates' => ['created_at' => ['Tanggal pengajuan', fn ($r) => self::date($r->created_at)], 'decided_at' => ['Tanggal diputuskan', fn ($r) => self::date($r->decided_at)]],
                'default_date' => 'created_at',
                'list' => ['code' => 'Kode', 'dim:type' => 'Jenis', 'dim:status' => 'Status', 'days_open' => 'Lama (hari)', 'date:created_at' => 'Diajukan'],
                'link' => fn ($r) => '/approvals',
                'sort' => 'days_open',
            ],
            'snapshots' => [
                'label' => 'Riwayat skor bulanan (snapshot)',
                'query' => fn () => $this->snapshots()->select(['id', 'risk_id', 'period', 'inherent_score', 'residual_score', 'level', 'status']),
                'dims' => [
                    'period' => ['Periode (bulan)', fn ($r) => (string) $r->period],
                    'level' => ['Level risiko', fn ($r) => $lvl($r->level), fn ($r) => $r->level],
                ],
                'measures' => [
                    'residual_score' => ['Skor residual', fn ($r) => (float) $r->residual_score, 'score'],
                    'inherent_score' => ['Skor inheren', fn ($r) => (float) $r->inherent_score, 'score'],
                ],
                'dates' => ['period' => ['Periode', fn ($r) => Carbon::createFromFormat('Y-m-d', $r->period . '-01')]],
                'default_date' => 'period',
                'list' => ['dim:period' => 'Periode', 'residual_score' => 'Skor residual', 'dim:level' => 'Level'],
                'link' => fn ($r) => "/risks/{$r->risk_id}",
                'sort' => 'residual_score',
                'order' => ['level' => $levelOrder],
            ],
        ];
    }

    /** Ringkasan skema untuk prompt AI (tanpa data). */
    public function schema(): array
    {
        return collect($this->defs())->map(fn ($d, $k) => [
            'dataset' => $k, 'label' => $d['label'],
            'dimensions' => collect($d['dims'])->map(fn ($x) => $x[0])->all(),
            'measures' => collect($d['measures'])->map(fn ($x) => $x[0])->all(),
            'dates' => collect($d['dates'])->map(fn ($x) => $x[0])->all(),
        ])->values()->all();
    }
}

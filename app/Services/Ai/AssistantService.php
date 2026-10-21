<?php

namespace App\Services\Ai;

use App\Models\ActionPlan;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Control;
use App\Models\Document;
use App\Models\FrameworkItem;
use App\Models\Incident;
use App\Models\Objective;
use App\Models\RiskCategory;
use App\Models\Risk;
use App\Models\RmiAssessment;
use App\Models\Scope;
use App\Models\User;
use App\Services\AiService;
use App\Support\Scoring;
use App\Support\UnitScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * AI Assistant kontekstual yang terpasang di setiap halaman fitur. Untuk setiap modul,
 * asisten menyusun "snapshot" data halaman itu (dibatasi cakupan unit pengguna, tanpa data
 * pribadi seperti nama orang), lalu menjawab aksi cepat atau pertanyaan bebas pengguna.
 * Tanpa kunci API, analisis disusun dari aturan atas snapshot yang sama.
 */
class AssistantService
{
    /** Modul: label, aksi cepat [kunci => [label, instruksi]], peran yang boleh (null = semua kecuali auditor). */
    public const MODULES = [
        'dashboard' => ['Risk Dashboard', ['summary' => 'Ringkas profil risiko', 'priority' => 'Apa yang harus diprioritaskan?', 'changes' => 'Risiko yang memburuk']],
        'executive' => ['Executive Dashboard', ['summary' => 'Ringkasan untuk pimpinan', 'priority' => 'Keputusan yang dibutuhkan', 'limit' => 'Eksposur vs risk limit']],
        'risks' => ['Risk Register', ['summary' => 'Analisis profil register', 'priority' => 'Risiko prioritas', 'quality' => 'Periksa kualitas data register']],
        'risk' => ['Detail risiko', ['explain' => 'Jelaskan skor & evaluasi', 'controls' => 'Usulkan kontrol', 'treatment' => 'Rekomendasi treatment & action plan', 'kri' => 'Usulkan KRI pemantau']],
        'risk_form' => ['Formulir risiko', ['guide' => 'Panduan mengisi formulir', 'statement' => 'Contoh pernyataan risiko yang baik']],
        'matrix' => ['Peta risiko', ['summary' => 'Baca sebaran peta risiko', 'priority' => 'Sel yang perlu perhatian']],
        'evaluation' => ['Evaluasi risiko', ['summary' => 'Analisis evaluasi vs appetite', 'limit' => 'Pemakaian risk limit']],
        'residual' => ['Monitoring residual', ['changes' => 'Risiko yang memburuk', 'summary' => 'Analisis tren residual']],
        'reviews' => ['Risk review', ['summary' => 'Status review periode ini', 'priority' => 'Risiko yang harus direview dulu']],
        'context' => ['Konteks & konsultasi', ['summary' => 'Ringkas konteks & KKS', 'kks' => 'Telaah kajian KKS']],
        'criteria' => ['Kriteria & taksonomi', ['summary' => 'Analisis appetite & taksonomi', 'limit' => 'Kategori dekat batas']],
        'action_plans' => ['Mitigasi & action plan', ['summary' => 'Analisis status action plan', 'overdue' => 'Tindak lanjut yang terlambat', 'budget' => 'Anggaran vs realisasi']],
        'action_plan' => ['Detail action plan', ['next' => 'Langkah berikutnya', 'effect' => 'Dampak ke skor risiko']],
        'controls' => ['Kontrol & efektivitas', ['summary' => 'Analisis efektivitas kontrol', 'weak' => 'Kontrol lemah & uji jatuh tempo', 'gaps' => 'Risiko tanpa kontrol']],
        'control' => ['Detail kontrol', ['assess' => 'Tinjau efektivitas kontrol', 'test' => 'Susun rencana pengujian']],
        'improvements' => ['Perbaikan berkelanjutan', ['summary' => 'Analisis perbaikan', 'overdue' => 'Perbaikan terlambat']],
        'incidents' => ['Insiden', ['summary' => 'Analisis pola insiden', 'rootcause' => 'Pola akar penyebab', 'loss' => 'Analisis kerugian']],
        'incident' => ['Detail insiden', ['rootcause' => 'Analisis akar penyebab (5 why)', 'lessons' => 'Lesson learned & pencegahan']],
        'losses' => ['Loss Event Database', ['summary' => 'Analisis tren kerugian', 'loss' => 'Kategori kerugian terbesar']],
        'kris' => ['KRI & early warning', ['summary' => 'Analisis status KRI', 'breach' => 'KRI yang melewati ambang', 'thresholds' => 'Tinjau ambang KRI']],
        'alerts' => ['Peringatan', ['priority' => 'Prioritaskan peringatan', 'summary' => 'Ringkas peringatan']],
        'objectives' => ['Pemetaan sasaran', ['summary' => 'Sasaran yang paling berisiko', 'gaps' => 'Sasaran tanpa risiko']],
        'units' => ['Struktur organisasi', ['summary' => 'Profil risiko per unit', 'priority' => 'Unit yang perlu perhatian']],
        'framework' => ['Kerangka ISO 31000', ['summary' => 'Analisis kesenjangan kerangka', 'priority' => 'Butir yang harus dibenahi dulu']],
        'rmi' => ['Risk Maturity Index', ['summary' => 'Analisis tingkat kematangan', 'improve' => 'Rekomendasi peningkatan']],
        'rmi_assessment' => ['Penilaian RMI', ['summary' => 'Analisis skor per dimensi', 'improve' => 'Parameter yang harus ditingkatkan']],
        'approvals' => ['Persetujuan', ['summary' => 'Analisis antrean persetujuan', 'priority' => 'Pengajuan yang harus diputuskan dulu']],
        'reports' => ['Laporan', ['draft' => 'Draf naskah laporan', 'summary' => 'Poin penting untuk laporan']],
        'documents' => ['Dokumen & bukti', ['summary' => 'Analisis dokumen', 'expiring' => 'Dokumen kedaluwarsa']],
        'audit' => ['Audit trail', ['summary' => 'Ringkas aktivitas 30 hari', 'anomaly' => 'Aktivitas yang perlu dicek']],
        'users' => ['Pengguna & akun', ['summary' => 'Analisis akun & peran', 'security' => 'Rekomendasi keamanan akun']],
        'settings' => ['Pengaturan organisasi', ['summary' => 'Jelaskan pengaturan', 'security' => 'Rekomendasi kebijakan']],
        'general' => ['ManRisk ERM', ['summary' => 'Ringkas kondisi risiko', 'help' => 'Apa yang bisa saya lakukan di sini?']],
    ];

    /** Modul yang dibatasi peran (sama dengan rute halamannya). */
    private const ROLE_LIMITS = [
        'audit' => ['super_admin', 'risk_admin', 'risk_manager', 'board'],
        'users' => ['super_admin'],
        'settings' => ['super_admin', 'risk_admin'],
    ];

    private const ACTION_TASKS = [
        'summary' => 'Buat ringkasan analitis 4–6 poin: kondisi saat ini, angka penting, dan apa artinya.',
        'priority' => 'Tentukan 3–5 prioritas tindakan yang paling berdampak, urut dari yang terpenting, beserta alasannya.',
        'changes' => 'Jelaskan risiko yang memburuk atau naik skornya dan tindakan yang disarankan.',
        'limit' => 'Analisis eksposur dibandingkan risk limit/appetite dan sebutkan kategori yang mendekati atau melampaui.',
        'quality' => 'Periksa kualitas data: risiko tanpa kontrol, tanpa action plan, skor target tidak realistis, review tertunda. Beri daftar perbaikan.',
        'explain' => 'Jelaskan skor inheren, residual, target, eksposur, dan status evaluasi terhadap appetite/tolerance dalam bahasa sederhana.',
        'controls' => 'Usulkan 3–5 kontrol (preventif/detektif/korektif) yang belum ada beserta frekuensi dan indikator efektivitasnya.',
        'treatment' => 'Rekomendasikan opsi treatment dan 3 action plan konkret (PIC generik, tenggat, perkiraan penurunan L/I).',
        'kri' => 'Usulkan 2–3 KRI beserta satuan, frekuensi, arah, ambang waspada dan kritis.',
        'guide' => 'Berikan panduan langkah demi langkah mengisi formulir risiko (konteks, penyebab–peristiwa–dampak, skor, eksposur, target).',
        'statement' => 'Berikan 2 contoh pernyataan risiko yang baik (penyebab → peristiwa → dampak) relevan dengan kategori organisasi.',
        'kks' => 'Telaah kajian Kegiatan Korporasi Signifikan: kelengkapan, risiko utama, dan hal yang perlu diputuskan organ pemutus.',
        'overdue' => 'Analisis item yang terlambat: penyebab umum, dampaknya ke risiko, dan langkah eskalasi/tindak lanjut.',
        'budget' => 'Analisis anggaran vs realisasi biaya, efisiensi, dan action plan yang perlu ditinjau.',
        'next' => 'Sarankan langkah berikutnya agar action plan selesai tepat waktu, termasuk bukti yang perlu diunggah.',
        'effect' => 'Jelaskan dampak penyelesaian action plan terhadap skor residual dan target risiko.',
        'weak' => 'Analisis kontrol lemah dan yang uji efektivitasnya jatuh tempo; usulkan perbaikan.',
        'gaps' => 'Identifikasi celah (objek tanpa tautan/kontrol/risiko) dan cara menutupnya.',
        'assess' => 'Tinjau efektivitas desain dan operasi kontrol berdasarkan riwayat uji; beri rekomendasi.',
        'test' => 'Susun rencana pengujian kontrol: tujuan, sampel, langkah uji, bukti, kriteria lulus.',
        'rootcause' => 'Lakukan analisis akar penyebab (5 why/fishbone ringkas) dan tindakan pencegahan.',
        'loss' => 'Analisis kerugian: tren, kategori/unit terbesar, dan rekomendasi pengendalian.',
        'lessons' => 'Rumuskan lesson learned dan tindakan pencegahan yang dapat dibagikan ke unit lain.',
        'breach' => 'Analisis KRI yang melewati ambang: risiko terkait, urgensi, dan tindakan.',
        'thresholds' => 'Tinjau kewajaran ambang KRI terhadap nilai historis dan sarankan penyesuaian.',
        'improve' => 'Berikan rekomendasi peningkatan kematangan yang konkret per dimensi/parameter terendah.',
        'draft' => 'Susun draf naskah laporan manajemen risiko (pendahuluan, profil risiko, risiko utama, mitigasi, KRI & insiden, rekomendasi) dengan heading markdown.',
        'expiring' => 'Daftar dokumen yang kedaluwarsa/akan kedaluwarsa dan tindakan pembaruannya.',
        'anomaly' => 'Cari pola aktivitas yang tidak biasa (penghapusan, perubahan massal) yang perlu diperiksa.',
        'security' => 'Beri rekomendasi keamanan dan tata kelola akun/pengaturan berdasarkan data.',
        'help' => 'Jelaskan fitur ManRisk ERM yang relevan dan cara memanfaatkannya, termasuk AI Analitik untuk membuat dashboard.',
    ];

    public function __construct(private Datasets $data, private AiService $ai)
    {
    }

    public static function allowed(User $user, string $module): bool
    {
        if ($user->role === 'auditor' || !isset(self::MODULES[$module])) {
            return false;
        }
        return !isset(self::ROLE_LIMITS[$module]) || in_array($user->role, self::ROLE_LIMITS[$module], true);
    }

    /** Info modul untuk panel (label dan aksi cepat). */
    public static function info(string $module): array
    {
        $m = self::MODULES[$module] ?? self::MODULES['general'];
        return ['module' => isset(self::MODULES[$module]) ? $module : 'general', 'label' => $m[0], 'actions' => collect($m[1])->map(fn ($l, $k) => ['key' => $k, 'label' => $l])->values()->all()];
    }

    /**
     * @param array<int, array{role: string, content: string}> $history
     */
    public function ask(string $module, ?int $id, ?string $action, ?string $question, array $history = []): array
    {
        $user = $this->data->user();
        $snap = $this->snapshot($module, $id);
        $label = self::MODULES[$module][0];
        $actionLabel = $action ? (self::MODULES[$module][1][$action] ?? null) : null;
        $task = $action ? (self::ACTION_TASKS[$action] ?? self::ACTION_TASKS['summary']) : null;
        $question = $question ? trim(mb_substr(strip_tags($question), 0, 1500)) : null;
        $userMsg = $question ?: ($actionLabel . '. ' . $task);
        $system = "Anda adalah AI Assistant ManRisk ERM (manajemen risiko ISO 31000:2018 dan PER-2/MBU/03/2023) yang tertanam di halaman \"{$label}\"."
            . " Jawab dalam Bahasa Indonesia, ringkas dan terstruktur (markdown: poin, heading kecil bila perlu). Gunakan HANYA data halaman di bawah;"
            . ' bila data tidak cukup, katakan apa yang kurang. Sebut kode objek (mis. R-2026-001, AP-2026-003) agar dapat ditautkan.'
            . ' Hasil Anda adalah saran; keputusan tetap pada pemilik risiko dan pejabat berwenang.'
            . ($task && $question ? "\nKonteks tugas: {$task}" : '')
            . "\n\nData halaman (dibatasi hak akses pengguna):\n" . json_encode(array_diff_key($snap, ['refs' => 1]), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $messages = [];
        foreach (array_slice($history, -6) as $h) {
            if (in_array($h['role'] ?? '', ['user', 'assistant'], true) && trim((string) ($h['content'] ?? '')) !== '') {
                $messages[] = ['role' => $h['role'], 'content' => mb_substr(strip_tags((string) $h['content']), 0, 3000)];
            }
        }
        // percakapan harus diawali pengguna dan berganti peran
        while ($messages && $messages[0]['role'] !== 'user') {
            array_shift($messages);
        }
        if ($messages && end($messages)['role'] === 'user') {
            array_pop($messages);
        }
        $messages[] = ['role' => 'user', 'content' => $userMsg];
        $res = $this->ai->complete($user, 'assist:' . $module, $system, $messages, fn () => $this->local($snap, $action, $question, $label));
        return $res + ['refs' => $snap['refs'] ?? []];
    }

    // ---------- snapshot per modul ----------

    public function snapshot(string $module, ?int $id): array
    {
        return match ($module) {
            'dashboard', 'executive', 'risks', 'matrix', 'residual', 'reports', 'general' => $this->riskProfile($module),
            'evaluation', 'criteria' => $this->criteriaProfile(),
            'risk' => $this->risk($id),
            'risk_form' => $this->riskForm(),
            'reviews' => $this->reviews(),
            'context' => $this->context(),
            'action_plans' => $this->actionPlans(),
            'action_plan' => $this->actionPlan($id),
            'controls' => $this->controls(),
            'control' => $this->control($id),
            'improvements' => $this->improvements(),
            'incidents' => $this->incidents(),
            'incident' => $this->incident($id),
            'losses' => $this->losses(),
            'kris' => $this->kris(),
            'alerts' => $this->alerts(),
            'objectives' => $this->objectives(),
            'units' => $this->units(),
            'framework' => $this->framework(),
            'rmi' => $this->rmi(),
            'rmi_assessment' => $this->rmiAssessment($id),
            'approvals' => $this->approvals(),
            'documents' => $this->documents(),
            'audit' => $this->audit(),
            'users' => $this->users(),
            'settings' => $this->settings(),
            default => $this->riskProfile('general'),
        };
    }

    private function lvl(?string $k): string
    {
        return Scoring::levelLabel((string) $k);
    }

    private function riskRow(Risk $r): array
    {
        return ['kode' => $r->code, 'nama' => Str::limit($r->name, 90), 'unit' => $this->data->unitName($r->unit_id), 'kategori' => $this->data->categoryName($r->category_id), 'level' => $this->lvl($r->residual_level),
            'skor_inheren' => $r->inherent_score, 'skor_residual' => $r->residual_score, 'skor_target' => $r->target_score, 'evaluasi' => Scoring::EVALUATIONS[$r->evaluation] ?? $r->evaluation, 'tren' => Datasets::TRENDS[$r->trend] ?? $r->trend,
            'eksposur_residual' => $r->residual_exposure !== null ? (float) $r->residual_exposure : null];
    }

    private function countBy(Collection $c, callable $fn, int $take = 10): array
    {
        return $c->groupBy(fn ($x) => (string) $fn($x))->map->count()->sortDesc()->take($take)->all();
    }

    private function refs(iterable $models, string $prefix, string $col = 'code'): array
    {
        $out = [];
        foreach ($models as $m) {
            if ($m->{$col}) {
                $out[$m->{$col}] = $prefix . $m->id;
            }
        }
        return $out;
    }

    private function openPlans(): Collection
    {
        return $this->data->actionPlans()->whereNull('cancelled_at')->with('risk:id,code,unit_id')->get();
    }

    private function riskProfile(string $module): array
    {
        $risks = $this->data->risks()->where('status', '!=', 'closed')->get();
        $plans = $this->openPlans();
        $overdue = $plans->filter(fn ($p) => $p->computedStatus() === 'overdue');
        $kriBreach = $this->data->kris()->whereIn('status', ['warning', 'critical'])->count();
        $noControls = $this->data->risks()->where('status', '!=', 'closed')->doesntHave('controls')->count();
        $top = $risks->sortByDesc('residual_score')->take(10);
        $up = $risks->where('trend', 'up')->sortByDesc('residual_score')->take(8);
        $high = $risks->whereIn('residual_level', ['high', 'very_high']);
        $esc = $risks->whereIn('evaluation', ['escalate', 'critical']);
        $incYear = $this->data->incidents()->whereYear('occurred_at', now()->year)->get(['id', 'loss_amount']);
        $facts = [
            'risiko_aktif' => $risks->count(), 'risiko_level_tinggi' => $high->count(), 'rata_skor_residual' => round((float) $risks->avg('residual_score'), 1),
            'perlu_eskalasi_atau_kritis' => $esc->count(), 'skor_naik' => $risks->where('trend', 'up')->count(), 'skor_turun' => $risks->where('trend', 'down')->count(),
            'eksposur_residual_total' => (float) $risks->sum('residual_exposure'), 'risiko_tanpa_kontrol' => $noControls,
            'action_plan_aktif' => $plans->whereNotIn('id', $plans->filter(fn ($p) => $p->computedStatus() === 'done')->pluck('id'))->count(), 'action_plan_terlambat' => $overdue->count(),
            'kri_melewati_ambang' => $kriBreach, 'insiden_tahun_ini' => $incYear->count(), 'kerugian_tahun_ini' => (float) $incYear->sum('loss_amount'),
        ];
        $lists = [
            'per_level' => $this->countBy($risks, fn ($r) => $this->lvl($r->residual_level)),
            'per_evaluasi' => $this->countBy($risks, fn ($r) => Scoring::EVALUATIONS[$r->evaluation] ?? $r->evaluation),
            'per_unit' => $this->countBy($risks, fn ($r) => $this->data->unitName($r->unit_id), 8),
            'per_kategori' => $this->countBy($risks, fn ($r) => $this->data->categoryName($r->category_id), 8),
            'risiko_teratas' => $top->map(fn ($r) => $this->riskRow($r))->values()->all(),
            'risiko_skor_naik' => $up->map(fn ($r) => ['kode' => $r->code, 'nama' => Str::limit($r->name, 80), 'skor_sebelumnya' => $r->previous_score, 'skor_residual' => $r->residual_score])->values()->all(),
            'action_plan_terlambat' => $overdue->sortBy('due_date')->take(8)->map(fn ($p) => ['kode' => $p->code, 'judul' => Str::limit($p->title, 80), 'risiko' => $p->risk?->code, 'tenggat' => $p->due_date?->format('Y-m-d'), 'progres' => $p->progress])->values()->all(),
        ];
        if ($module === 'matrix') {
            $lists['sebaran_matriks_residual'] = $risks->groupBy(fn ($r) => "L{$r->residual_l}×I{$r->residual_i}")->map->count()->sortDesc()->all();
        }
        $insights = [];
        if ($risks->count()) {
            $pct = round($high->count() / $risks->count() * 100);
            $insights[] = "{$high->count()} dari {$risks->count()} risiko aktif ({$pct}%) berlevel tinggi.";
        }
        if ($esc->count()) {
            $insights[] = "{$esc->count()} risiko berstatus perlu eskalasi/kritis dan memerlukan keputusan manajemen: " . $esc->sortByDesc('residual_score')->take(4)->pluck('code')->implode(', ') . '.';
        }
        if ($facts['skor_naik'] > $facts['skor_turun']) {
            $insights[] = "Profil risiko cenderung memburuk: {$facts['skor_naik']} risiko naik skornya, {$facts['skor_turun']} turun.";
        } elseif ($facts['skor_turun'] > 0) {
            $insights[] = "Profil risiko membaik: {$facts['skor_turun']} risiko turun skornya, {$facts['skor_naik']} naik.";
        }
        if ($u = array_key_first($lists['per_unit'])) {
            $insights[] = "Unit dengan risiko terbanyak: {$u} ({$lists['per_unit'][$u]} risiko).";
        }
        $recs = [];
        if ($top->first()) {
            $recs[] = 'Fokuskan mitigasi pada risiko dengan skor tertinggi: ' . $top->take(3)->map(fn ($r) => "{$r->code} ({$r->residual_score})")->implode(', ') . '.';
        }
        if ($overdue->count()) {
            $recs[] = "Tindak lanjuti {$overdue->count()} action plan terlambat dan eskalasi ke Risk Manager bila tidak ada kemajuan.";
        }
        if ($noControls) {
            $recs[] = "Tautkan kontrol untuk {$noControls} risiko yang belum memiliki kontrol.";
        }
        if ($kriBreach) {
            $recs[] = "Tinjau {$kriBreach} KRI yang melewati ambang dan pastikan perbaikan berjalan.";
        }
        $refs = $this->refs($risks, '/risks/') + $this->refs($overdue, '/action-plans/');
        return ['halaman' => self::MODULES[$module][0], 'facts' => $facts, 'lists' => $lists, 'insights' => $insights, 'recs' => $recs, 'refs' => $refs];
    }

    private function criteriaProfile(): array
    {
        $risks = $this->data->risks()->where('status', '!=', 'closed')->get(['id', 'code', 'category_id', 'residual_score', 'residual_exposure', 'evaluation']);
        $cats = RiskCategory::where('active', true)->orderBy('sort')->get();
        $rows = $cats->map(function ($c) use ($risks) {
            $rs = $risks->where('category_id', $c->id);
            $exp = (float) $rs->sum('residual_exposure');
            return ['kategori' => $c->name, 'kode_bumn' => $c->bumn_code, 'appetite' => $c->appetite, 'tolerance' => $c->tolerance, 'risk_limit' => $c->risk_limit !== null ? (float) $c->risk_limit : null,
                'jumlah_risiko' => $rs->count(), 'melewati_toleransi' => $rs->filter(fn ($r) => $r->residual_score > $c->tolerance)->count(), 'eksposur_residual' => $exp,
                'pemakaian_limit_persen' => $c->risk_limit ? round($exp / (float) $c->risk_limit * 100) : null];
        })->filter(fn ($r) => $r['jumlah_risiko'] > 0 || $r['risk_limit'])->values();
        $over = $rows->filter(fn ($r) => ($r['pemakaian_limit_persen'] ?? 0) >= 80)->sortByDesc('pemakaian_limit_persen');
        $tol = $rows->filter(fn ($r) => $r['melewati_toleransi'] > 0)->sortByDesc('melewati_toleransi');
        $insights = [];
        foreach ($over->take(4) as $r) {
            $insights[] = "Kategori {$r['kategori']} memakai {$r['pemakaian_limit_persen']}% risk limit" . ($r['pemakaian_limit_persen'] >= 100 ? ' (melampaui).' : ' (mendekati batas).');
        }
        foreach ($tol->take(3) as $r) {
            $insights[] = "{$r['melewati_toleransi']} risiko kategori {$r['kategori']} melewati toleransi ({$r['tolerance']}).";
        }
        $recs = $over->count() ? ['Tinjau pernyataan risk appetite dan tetapkan langkah pengurangan eksposur untuk kategori yang mendekati/melampaui limit.'] : ['Pemakaian risk limit masih di bawah 80%; pertahankan pemantauan triwulanan.'];
        return ['halaman' => 'Kriteria, appetite dan risk limit', 'facts' => ['skema_level' => Scoring::SCHEMES[Scoring::currentScheme()]['label'], 'kategori_aktif' => $cats->count(), 'kategori_dekat_atau_lewat_limit' => $over->count()],
            'lists' => ['kategori' => $rows->sortByDesc('jumlah_risiko')->take(20)->values()->all(), 'evaluasi' => $this->countBy($risks, fn ($r) => Scoring::EVALUATIONS[$r->evaluation] ?? $r->evaluation)],
            'insights' => $insights, 'recs' => $recs, 'refs' => []];
    }

    private function risk(?int $id): array
    {
        $r = Risk::with(['category', 'controls', 'actionPlans', 'kris', 'reviews' => fn ($q) => $q->latest()->limit(4), 'quarterTargets'])->findOrFail((int) $id);
        $this->authorizeView($r);
        $ctrl = $r->controls->map(fn ($c) => ['kode' => $c->code, 'nama' => $c->name, 'tipe' => Datasets::CONTROL_TYPES[$c->type] ?? $c->type, 'efektivitas' => $c->overallEffectiveness() ? config('manrisk.effectiveness')[$c->overallEffectiveness()] : 'Belum diuji'])->values();
        $plans = $r->actionPlans->whereNull('cancelled_at')->map(fn ($p) => ['kode' => $p->code, 'judul' => $p->title, 'status' => Datasets::PLAN_STATUSES[$p->computedStatus()], 'progres' => $p->progress, 'tenggat' => $p->due_date?->format('Y-m-d'), 'penurunan_L' => $p->expected_dl, 'penurunan_I' => $p->expected_di])->values();
        $kris = $r->kris->map(fn ($k) => ['kode' => $k->code, 'nama' => $k->name, 'nilai_terakhir' => $k->last_value !== null ? (float) $k->last_value : null, 'ambang_waspada' => (float) $k->threshold_warn, 'ambang_kritis' => (float) $k->threshold_crit, 'status' => Datasets::KRI_STATUSES[$k->status] ?? $k->status])->values();
        $inc = Incident::where('risk_id', $r->id)->get(['id', 'loss_amount']);
        $cat = $r->category;
        $facts = $this->riskRow($r) + ['pernyataan' => ['penyebab' => Str::limit($r->cause, 400), 'peristiwa' => Str::limit($r->event, 400), 'dampak' => Str::limit($r->impact, 400)],
            'kontrol_yang_ada_teks' => Str::limit((string) $r->existing_controls, 400), 'L_I_inheren' => "L{$r->inherent_l}×I{$r->inherent_i}", 'L_I_residual' => "L{$r->residual_l}×I{$r->residual_i}", 'L_I_target' => "L{$r->target_l}×I{$r->target_i}",
            'appetite_kategori' => $cat?->appetite, 'toleransi_kategori' => $cat?->tolerance, 'treatment' => config('manrisk.treatments')[$r->treatment] ?? $r->treatment, 'status' => config('manrisk.risk_statuses')[$r->status] ?? $r->status,
            'sumber' => config('manrisk.source_kinds')[$r->source_kind] ?? $r->source_kind, 'insiden_terkait' => $inc->count(), 'kerugian_insiden' => (float) $inc->sum('loss_amount')];
        $insights = [];
        if ($cat && $r->residual_score > $cat->tolerance) {
            $insights[] = "Skor residual {$r->residual_score} melewati toleransi kategori ({$cat->tolerance}); perlu penanganan segera.";
        } elseif ($cat && $r->residual_score > $cat->appetite) {
            $insights[] = "Skor residual {$r->residual_score} di atas appetite ({$cat->appetite}) namun masih dalam toleransi ({$cat->tolerance}).";
        } else {
            $insights[] = "Skor residual {$r->residual_score} masih dalam appetite kategori.";
        }
        $reduction = $r->inherent_score > 0 ? round((1 - $r->residual_score / $r->inherent_score) * 100) : 0;
        $insights[] = $reduction > 0 ? "Kontrol yang ada menurunkan skor {$reduction}% dari inheren {$r->inherent_score} ke residual {$r->residual_score}; target {$r->target_score}."
            : "Kontrol yang ada belum menurunkan skor (inheren = residual = {$r->residual_score}); target {$r->target_score}.";
        if ($ctrl->isEmpty()) {
            $insights[] = 'Risiko ini belum ditautkan ke kontrol mana pun.';
        }
        if ($plans->isEmpty() && $r->treatment !== 'retain') {
            $insights[] = 'Belum ada action plan untuk mencapai skor target.';
        }
        if ($kris->isEmpty()) {
            $insights[] = 'Belum ada KRI yang memantau risiko ini.';
        }
        $recs = $this->riskRecs($r, $ctrl, $plans, $kris);
        return ['halaman' => "Detail risiko {$r->code}", 'facts' => $facts, 'lists' => ['kontrol' => $ctrl->all(), 'action_plan' => $plans->all(), 'kri' => $kris->all(),
            'review_terakhir' => $r->reviews->map(fn ($v) => ['periode' => $v->period, 'skor' => $v->current_score, 'keputusan' => Datasets::REVIEW_DECISIONS[$v->decision] ?? $v->decision])->all(),
            'target_triwulan' => $r->quarterTargets->sortBy(['year', 'quarter'])->map(fn ($t) => ['periode' => "{$t->year}-T{$t->quarter}", 'skor_target' => $t->target_score])->values()->all()],
            'insights' => $insights, 'recs' => $recs, 'refs' => [$r->code => "/risks/{$r->id}"] + $this->refs($r->actionPlans, '/action-plans/') + $this->refs($r->controls, '/controls/')];
    }

    private function riskRecs(Risk $r, Collection $ctrl, Collection $plans, Collection $kris): array
    {
        $recs = [];
        $cause = Str::limit($r->cause, 70);
        $event = Str::limit($r->event, 70);
        $impact = Str::limit($r->impact, 70);
        $recs['controls'] = ["*Preventif*: SOP, otorisasi, dan checklist untuk mencegah penyebab “{$cause}” (bulanan).", "*Detektif*: pemantauan indikator dan rekonsiliasi berkala untuk mendeteksi “{$event}” lebih dini (mingguan).", "*Korektif*: rencana tanggap dan eskalasi bila dampak “{$impact}” terjadi; uji efektivitas desain dan operasi setiap triwulan."];
        $opt = $r->residual_score >= 20 ? 'avoid' : ($r->residual_score >= 5 ? 'reduce' : 'retain');
        $recs['treatment'] = ['Opsi yang disarankan: **' . config('manrisk.treatments')[$opt] . '** (skor residual ' . $r->residual_score . ').', 'Perkuat kontrol kunci dan tetapkan PIC (30 hari) — perkiraan ΔL −1.', 'Tambahkan kontrol detektif/KRI dengan ambang peringatan (60 hari) — perkiraan ΔI −1.', "Sosialisasi/pelatihan pelaksana proses (90 hari) untuk mencapai target {$r->target_score}."];
        $recs['kri'] = ["Frekuensi kejadian “{$event}” per bulan (arah naik buruk; waspada = rata-rata historis, kritis = 1,5× rata-rata).", 'Persentase pelaksanaan kontrol kunci tepat waktu (arah turun buruk; waspada < 90%, kritis < 75%).', 'Nilai kerugian/dampak finansial per triwulan (arah naik buruk; ambang mengikuti risk limit kategori).'];
        $recs['explain'] = ["Inheren {$r->inherent_score} (L{$r->inherent_l}×I{$r->inherent_i}) = paparan sebelum kontrol.", "Residual {$r->residual_score} (L{$r->residual_l}×I{$r->residual_i}) = setelah kontrol yang ada; level " . $this->lvl($r->residual_level) . '.', "Target {$r->target_score} (L{$r->target_l}×I{$r->target_i}) dicapai bila action plan selesai sesuai rencana."];
        return $recs;
    }

    private function riskForm(): array
    {
        $cats = RiskCategory::where('active', true)->orderBy('sort')->limit(20)->pluck('name')->all();
        return ['halaman' => 'Formulir identifikasi risiko', 'facts' => ['kategori_tersedia' => $cats, 'skema_level' => Scoring::SCHEMES[Scoring::currentScheme()]['label']], 'lists' => [],
            'insights' => [], 'recs' => ['guide' => ['Langkah 1 Identifikasi: pilih unit, pemilik, kategori (T3), sasaran/proses, lalu tulis penyebab → peristiwa → dampak. Gunakan tombol Saran risiko AI bila perlu.',
                'Langkah 2 Analisis: nilai kemungkinan dan dampak (1–5) per dimensi untuk inheren dan residual; isi eksposur Rp bila dapat dikuantifikasi.', 'Langkah 3 Evaluasi & treatment: pilih opsi treatment; tetapkan skor target dan target per triwulan (≤ residual).',
                'Langkah 4 Tinjau & simpan: simpan sebagai draft lalu ajukan persetujuan. Periksa peringatan duplikasi sebelum mengajukan.'],
                'statement' => ['Karena *aplikasi layanan berjalan di satu server tanpa cadangan*, dapat terjadi *layanan berhenti saat server gagal*, sehingga *masyarakat tidak terlayani dan reputasi turun*.', 'Karena *verifikasi dokumen pengadaan dilakukan manual*, dapat terjadi *penyedia tidak memenuhi syarat terpilih*, sehingga *kontrak bermasalah dan timbul kerugian negara*.']], 'refs' => []];
    }

    private function reviews(): array
    {
        $risks = $this->data->risks()->where('status', '!=', 'closed')->get(['id', 'code', 'name', 'unit_id', 'residual_score', 'residual_level']);
        $period = now()->format('Y') . '-Q' . now()->quarter;
        $reviews = $this->data->reviews()->with('risk:id,code')->latest()->limit(500)->get();
        $reviewedIds = $reviews->where('period', $period)->pluck('risk_id')->unique();
        $due = $risks->whereNotIn('id', $reviewedIds)->sortByDesc('residual_score');
        return ['halaman' => 'Risk review', 'facts' => ['periode_berjalan' => $period, 'risiko_aktif' => $risks->count(), 'sudah_direview_periode_ini' => $reviewedIds->count(), 'belum_direview' => $due->count()],
            'lists' => ['belum_direview_skor_tertinggi' => $due->take(10)->map(fn ($r) => ['kode' => $r->code, 'nama' => Str::limit($r->name, 80), 'skor_residual' => $r->residual_score, 'level' => $this->lvl($r->residual_level)])->values()->all(),
                'keputusan_review' => $this->countBy($reviews, fn ($v) => Datasets::REVIEW_DECISIONS[$v->decision] ?? $v->decision), 'tren_review' => $this->countBy($reviews, fn ($v) => Datasets::TRENDS[$v->trend] ?? $v->trend)],
            'insights' => [$risks->count() ? round($reviewedIds->count() / max(1, $risks->count()) * 100) . "% risiko aktif sudah direview pada periode {$period}." : 'Belum ada risiko aktif.'],
            'recs' => $due->count() ? ['Jadwalkan review untuk risiko berskor tertinggi lebih dulu: ' . $due->take(5)->pluck('code')->implode(', ') . '.', 'Ambil snapshot bulanan agar tren residual tercatat.'] : ['Seluruh risiko sudah direview periode ini.'],
            'refs' => $this->refs($due->take(10), '/risks/')];
    }

    private function context(): array
    {
        $scopes = Scope::withCount(['factors', 'consultations'])->latest()->limit(50)->get();
        $kks = $scopes->where('kind', 'kks');
        return ['halaman' => 'Konteks & konsultasi', 'facts' => ['ruang_lingkup' => $scopes->where('kind', '!=', 'kks')->count(), 'kajian_kks' => $kks->count(), 'faktor_konteks' => $scopes->sum('factors_count'), 'konsultasi' => $scopes->sum('consultations_count')],
            'lists' => ['kajian_kks' => $kks->take(10)->map(fn ($s) => ['nama' => $s->name, 'jenis' => config('manrisk.kks_types')[$s->kks_type] ?? $s->kks_type, 'nilai_transaksi' => (float) $s->transaction_value, 'organ_pemutus' => config('manrisk.decision_organs')[$s->decision_organ] ?? $s->decision_organ,
                'tanggal_keputusan' => Datasets::date($s->decision_date)?->format('Y-m-d'), 'kesimpulan_mr' => Str::limit((string) $s->conclusion, 300)])->values()->all(),
                'ruang_lingkup' => $scopes->where('kind', '!=', 'kks')->take(10)->map(fn ($s) => ['nama' => $s->name, 'periode' => $s->period, 'faktor' => $s->factors_count, 'konsultasi' => $s->consultations_count])->values()->all()],
            'insights' => array_values(array_filter([$kks->whereNull('conclusion')->count() ? $kks->whereNull('conclusion')->count() . ' kajian KKS belum memiliki kesimpulan fungsi manajemen risiko.' : null, $scopes->where('factors_count', 0)->count() ? $scopes->where('factors_count', 0)->count() . ' ruang lingkup belum memiliki faktor konteks (SWOT).' : null])),
            'recs' => ['Lengkapi kesimpulan MR dan tautkan register risiko pada setiap kajian KKS sebelum diajukan ke organ pemutus.', 'Pastikan setiap ruang lingkup memiliki faktor internal/eksternal dan catatan konsultasi pemangku kepentingan.'], 'refs' => []];
    }

    private function actionPlans(): array
    {
        $plans = $this->openPlans();
        $st = $plans->groupBy(fn ($p) => $p->computedStatus());
        $overdue = ($st['overdue'] ?? collect())->sortBy('due_date');
        $soon = $plans->filter(fn ($p) => in_array($p->computedStatus(), ['todo', 'running'], true) && $p->due_date && $p->due_date->between(now(), now()->addDays(14)))->sortBy('due_date');
        $budget = (float) $plans->sum('budget');
        $actual = (float) $plans->sum('actual_cost');
        $row = fn ($p) => ['kode' => $p->code, 'judul' => Str::limit($p->title, 80), 'risiko' => $p->risk?->code, 'unit' => $this->data->unitName($p->unit_id ?? $p->risk?->unit_id), 'tenggat' => $p->due_date?->format('Y-m-d'), 'progres' => $p->progress, 'prioritas' => config('manrisk.priorities')[$p->priority] ?? $p->priority,
            'anggaran' => (float) $p->budget, 'realisasi' => (float) $p->actual_cost];
        $facts = ['total' => $plans->count(), 'per_status' => collect($st)->mapWithKeys(fn ($g, $k) => [Datasets::PLAN_STATUSES[$k] => $g->count()])->all(), 'rata_progres' => round((float) $plans->avg('progress'), 1),
            'anggaran_total' => $budget, 'realisasi_total' => $actual, 'serapan_persen' => $budget > 0 ? round($actual / $budget * 100) : null];
        $insights = [];
        if ($overdue->count()) {
            $insights[] = "{$overdue->count()} action plan terlambat; tertua: " . $overdue->take(3)->map(fn ($p) => "{$p->code} ({$p->due_date?->format('d/m/Y')}, {$p->progress}%)")->implode(', ') . '.';
        }
        if ($soon->count()) {
            $insights[] = "{$soon->count()} action plan jatuh tempo dalam 14 hari.";
        }
        if (($st['verify'] ?? collect())->count()) {
            $insights[] = ($st['verify'])->count() . ' action plan menunggu verifikasi Risk Owner.';
        }
        if ($budget > 0) {
            $insights[] = 'Serapan anggaran ' . $facts['serapan_persen'] . '% (realisasi Rp ' . number_format($actual, 0, ',', '.') . ' dari Rp ' . number_format($budget, 0, ',', '.') . ').';
        }
        $byUnit = $overdue->groupBy(fn ($p) => $this->data->unitName($p->unit_id ?? $p->risk?->unit_id))->map->count()->sortDesc();
        return ['halaman' => 'Mitigasi & action plan', 'facts' => $facts, 'lists' => ['terlambat' => $overdue->take(10)->map($row)->values()->all(), 'jatuh_tempo_14_hari' => $soon->take(8)->map($row)->values()->all(), 'terlambat_per_unit' => $byUnit->take(8)->all(),
            'anggaran_terbesar' => $plans->sortByDesc('budget')->take(5)->map($row)->values()->all()],
            'insights' => $insights, 'recs' => array_values(array_filter([$overdue->count() ? 'Minta PIC memperbarui progres dan bukti untuk action plan terlambat; eskalasi yang tidak bergerak > 14 hari ke Risk Manager.' : null,
                $byUnit->count() ? 'Unit dengan keterlambatan terbanyak: ' . $byUnit->keys()->first() . ' — jadwalkan rapat tindak lanjut.' : null, ($st['verify'] ?? collect())->count() ? 'Percepat verifikasi Risk Owner agar status selesai tercatat.' : null,
                $budget > 0 && $facts['serapan_persen'] < 50 ? 'Serapan anggaran rendah; tinjau kendala pelaksanaan.' : null])) ?: ['Pelaksanaan action plan sesuai jadwal; pertahankan pemantauan mingguan.'],
            'refs' => $this->refs($plans, '/action-plans/')];
    }

    private function actionPlan(?int $id): array
    {
        $p = ActionPlan::with(['risk', 'progressLog' => fn ($q) => $q->latest()->limit(5)])->findOrFail((int) $id);
        $this->authorizeView($p);
        $r = $p->risk;
        $days = $p->due_date ? (int) now()->startOfDay()->diffInDays($p->due_date, false) : null;
        $facts = ['kode' => $p->code, 'judul' => $p->title, 'deskripsi' => Str::limit((string) $p->description, 500), 'status' => Datasets::PLAN_STATUSES[$p->computedStatus()], 'progres' => $p->progress, 'tenggat' => $p->due_date?->format('Y-m-d'), 'sisa_hari' => $days,
            'prioritas' => config('manrisk.priorities')[$p->priority] ?? $p->priority, 'anggaran' => (float) $p->budget, 'realisasi' => (float) $p->actual_cost, 'perkiraan_penurunan' => "ΔL −{$p->expected_dl}, ΔI −{$p->expected_di}",
            'risiko' => $r ? ['kode' => $r->code, 'nama' => $r->name, 'residual' => "L{$r->residual_l}×I{$r->residual_i} = {$r->residual_score}", 'target' => $r->target_score] : null];
        $proj = $r ? max(1, $r->residual_l - $p->expected_dl) * max(1, $r->residual_i - $p->expected_di) : null;
        $insights = [];
        if ($days !== null && $days < 0 && $p->progress < 100) {
            $insights[] = 'Terlambat ' . abs($days) . " hari dengan progres {$p->progress}%.";
        } elseif ($days !== null && $p->progress < 100) {
            $insights[] = "Sisa {$days} hari menuju tenggat; progres {$p->progress}%.";
        }
        if ($proj !== null) {
            $insights[] = "Bila selesai, skor residual {$r->code} diproyeksikan turun dari {$r->residual_score} ke sekitar {$proj} (target {$r->target_score}).";
        }
        return ['halaman' => "Action plan {$p->code}", 'facts' => $facts, 'lists' => ['progres_terakhir' => $p->progressLog->map(fn ($l) => ['dari' => $l->from_pct, 'ke' => $l->to_pct, 'catatan' => Str::limit((string) $l->note, 150), 'tanggal' => $l->created_at?->format('Y-m-d')])->all()],
            'insights' => $insights, 'recs' => ['next' => ['Pecah sisa pekerjaan menjadi tonggak mingguan dan catat progres setiap tonggak.', 'Unggah bukti pelaksanaan (berita acara, foto, laporan) sebelum menandai 100%.', $p->computedStatus() === 'overdue' ? 'Ajukan perpanjangan tenggat dengan alasan atau eskalasi ke Risk Manager.' : 'Koordinasikan dengan Risk Owner untuk verifikasi setelah selesai.'],
                'effect' => array_values(array_filter([$proj !== null ? "Proyeksi skor residual setelah selesai: {$proj} (dari {$r->residual_score})." : null, 'Penurunan aktual dinilai ulang saat review risiko berikutnya.']))],
            'refs' => [$p->code => "/action-plans/{$p->id}"] + ($r ? [$r->code => "/risks/{$r->id}"] : [])];
    }

    private function controls(): array
    {
        $ctrls = $this->data->controls()->where('active', true)->withCount('risks')->get();
        $eff = config('manrisk.effectiveness');
        $weak = $ctrls->filter(fn ($c) => ($o = $c->overallEffectiveness()) !== null && $o <= 2);
        $due = $ctrls->filter(fn ($c) => $c->next_test_at && Datasets::date($c->next_test_at)->lt(now()->startOfDay()));
        $untested = $ctrls->filter(fn ($c) => $c->overallEffectiveness() === null);
        $noCtrl = $this->data->risks()->where('status', '!=', 'closed')->doesntHave('controls')->orderByDesc('residual_score')->limit(10)->get(['id', 'code', 'name', 'residual_score']);
        $row = fn ($c) => ['kode' => $c->code, 'nama' => Str::limit($c->name, 80), 'tipe' => Datasets::CONTROL_TYPES[$c->type] ?? $c->type, 'efektivitas' => $c->overallEffectiveness() ? $eff[$c->overallEffectiveness()] : 'Belum diuji', 'uji_berikutnya' => $c->next_test_at ? Datasets::date($c->next_test_at)->format('Y-m-d') : null, 'risiko_dikendalikan' => $c->risks_count];
        return ['halaman' => 'Kontrol & efektivitas', 'facts' => ['kontrol_aktif' => $ctrls->count(), 'per_tipe' => $this->countBy($ctrls, fn ($c) => Datasets::CONTROL_TYPES[$c->type] ?? $c->type), 'kontrol_lemah' => $weak->count(), 'uji_jatuh_tempo' => $due->count(), 'belum_diuji' => $untested->count(), 'risiko_tanpa_kontrol' => $this->data->risks()->where('status', '!=', 'closed')->doesntHave('controls')->count()],
            'lists' => ['kontrol_lemah' => $weak->take(10)->map($row)->values()->all(), 'uji_jatuh_tempo' => $due->take(10)->map($row)->values()->all(), 'risiko_tanpa_kontrol' => $noCtrl->map(fn ($r) => ['kode' => $r->code, 'nama' => Str::limit($r->name, 80), 'skor_residual' => $r->residual_score])->all()],
            'insights' => array_values(array_filter([$weak->count() ? "{$weak->count()} kontrol dinilai tidak/sebagian efektif." : null, $due->count() ? "{$due->count()} kontrol melewati jadwal uji." : null, $untested->count() ? "{$untested->count()} kontrol belum pernah diuji." : null, $noCtrl->count() ? 'Risiko tanpa kontrol dengan skor tertinggi: ' . $noCtrl->take(3)->pluck('code')->implode(', ') . '.' : null])),
            'recs' => array_values(array_filter([$weak->count() ? 'Buat tindakan perbaikan untuk kontrol lemah dan uji ulang setelah perbaikan.' : null, $due->count() ? 'Jadwalkan uji efektivitas desain & operasi untuk kontrol jatuh tempo.' : null, $noCtrl->count() ? 'Tautkan atau rancang kontrol untuk risiko yang belum memiliki kontrol.' : null])) ?: ['Kontrol dalam kondisi baik; lanjutkan pengujian berkala.'],
            'refs' => $this->refs($ctrls, '/controls/') + $this->refs($noCtrl, '/risks/')];
    }

    private function control(?int $id): array
    {
        $c = Control::with(['assessments' => fn ($q) => $q->orderByDesc('tested_at')->limit(6), 'risks' => fn ($q) => $q->select('risks.id', 'code', 'name', 'residual_score', 'unit_id')])->findOrFail((int) $id);
        $this->authorizeView($c);
        $eff = config('manrisk.effectiveness');
        $ids = $this->data->user()->accessibleUnitIds();
        $risks = $c->risks->when($ids !== null, fn ($x) => $x->whereIn('unit_id', $ids));
        $facts = ['kode' => $c->code, 'nama' => $c->name, 'tujuan' => Str::limit((string) $c->objective, 300), 'deskripsi' => Str::limit((string) $c->description, 400), 'tipe' => Datasets::CONTROL_TYPES[$c->type] ?? $c->type, 'mode' => $c->mode, 'frekuensi' => $c->frequency,
            'efektivitas_desain' => $c->design_eff ? $eff[$c->design_eff] ?? $c->design_eff : 'Belum diuji', 'efektivitas_operasi' => $c->operating_eff ? $eff[$c->operating_eff] ?? $c->operating_eff : 'Belum diuji', 'uji_berikutnya' => $c->next_test_at ? Datasets::date($c->next_test_at)->format('Y-m-d') : null];
        $hist = $c->assessments->map(fn ($a) => ['tanggal' => Datasets::date($a->tested_at)->format('Y-m-d'), 'desain' => $a->design_eff, 'operasi' => $a->operating_eff, 'catatan' => Str::limit((string) $a->note, 150)])->all();
        $o = $c->overallEffectiveness();
        return ['halaman' => "Kontrol {$c->code}", 'facts' => $facts, 'lists' => ['riwayat_uji' => $hist, 'risiko_dikendalikan' => $risks->map(fn ($r) => ['kode' => $r->code, 'nama' => Str::limit($r->name, 80), 'skor_residual' => $r->residual_score])->values()->all()],
            'insights' => array_values(array_filter([$o === null ? 'Kontrol belum pernah diuji.' : "Efektivitas keseluruhan: {$eff[$o]}.", $c->next_test_at && Datasets::date($c->next_test_at)->lt(now()) ? 'Jadwal uji berikutnya sudah lewat.' : null])),
            'recs' => ['assess' => [$o !== null && $o <= 2 ? 'Perbaiki rancangan/pelaksanaan kontrol, catat sebagai improvement, lalu uji ulang.' : 'Pertahankan pelaksanaan dan dokumentasikan bukti setiap periode.', 'Bandingkan hasil uji terakhir dengan insiden pada risiko yang dikendalikan.'],
                'test' => ['Tujuan: memastikan kontrol dirancang dan dijalankan sesuai frekuensi (' . $c->frequency . ').', 'Sampel: 10–25 transaksi/pelaksanaan periode terakhir (lebih banyak untuk kontrol manual).', 'Langkah: walkthrough desain, inspeksi bukti, re-performance; catat pengecualian.', 'Kriteria lulus: tidak ada pengecualian material; nilai desain dan operasi ≥ Efektif (3).']],
            'refs' => [$c->code => "/controls/{$c->id}"] + $this->refs($risks, '/risks/')];
    }

    private function improvements(): array
    {
        $items = $this->data->improvements()->get();
        $late = $items->filter(fn ($i) => $i->status !== 'done' && $i->due_date && $i->due_date->lt(now()->startOfDay()))->sortBy('due_date');
        return ['halaman' => 'Perbaikan berkelanjutan', 'facts' => ['total' => $items->count(), 'per_status' => $this->countBy($items, fn ($i) => Datasets::IMPROVEMENT_STATUSES[$i->status] ?? $i->status), 'per_sumber' => $this->countBy($items, fn ($i) => Datasets::IMPROVEMENT_SOURCES[$i->source_type] ?? $i->source_type), 'terlambat' => $late->count()],
            'lists' => ['terlambat' => $late->take(10)->map(fn ($i) => ['kode' => $i->code, 'judul' => Str::limit($i->title, 80), 'sumber' => Datasets::IMPROVEMENT_SOURCES[$i->source_type] ?? $i->source_type, 'tenggat' => $i->due_date?->format('Y-m-d')])->values()->all()],
            'insights' => array_values(array_filter([$late->count() ? "{$late->count()} perbaikan melewati tenggat." : null, ($s = $items->where('status', '!=', 'done')->groupBy('source_type')->map->count()->sortDesc())->count() ? 'Sumber perbaikan terbuka terbanyak: ' . (Datasets::IMPROVEMENT_SOURCES[$s->keys()->first()] ?? $s->keys()->first()) . " ({$s->first()})." : null])),
            'recs' => ['Tetapkan PIC dan tenggat untuk setiap perbaikan terbuka; tutup perbaikan dengan bukti dan lesson learned.', 'Kelompokkan perbaikan berulang dari sumber yang sama menjadi satu program perbaikan.'],
            'refs' => []];
    }

    private function incidents(): array
    {
        $inc = $this->data->incidents()->with('risk:id,code')->get();
        $open = $inc->where('status', '!=', 'closed');
        $year = $inc->filter(fn ($i) => $i->occurred_at?->year === now()->year);
        $months = $inc->filter(fn ($i) => $i->occurred_at && $i->occurred_at->gte(now()->startOfMonth()->subMonths(11)))->groupBy(fn ($i) => $i->occurred_at->format('Y-m'))->map->count()->sortKeys()->all();
        $row = fn ($i) => ['kode' => $i->code, 'judul' => Str::limit($i->title, 80), 'tanggal' => $i->occurred_at?->format('Y-m-d'), 'unit' => $this->data->unitName($i->unit_id), 'status' => config('manrisk.incident_statuses')[$i->status] ?? $i->status, 'kerugian' => (float) $i->loss_amount, 'risiko' => $i->risk?->code];
        $byUnit = $this->countBy($inc, fn ($i) => $this->data->unitName($i->unit_id), 8);
        return ['halaman' => 'Insiden', 'facts' => ['total' => $inc->count(), 'terbuka' => $open->count(), 'tahun_ini' => $year->count(), 'kerugian_tahun_ini' => (float) $year->sum('loss_amount'), 'tanpa_risiko_terkait' => $inc->whereNull('risk_id')->count()],
            'lists' => ['per_bulan_12' => $months, 'per_unit' => $byUnit, 'per_status' => $this->countBy($inc, fn ($i) => config('manrisk.incident_statuses')[$i->status] ?? $i->status), 'kerugian_terbesar' => $inc->sortByDesc('loss_amount')->take(5)->map($row)->values()->all(),
                'penyebab_terbaru' => $inc->sortByDesc('occurred_at')->take(10)->map(fn ($i) => ['kode' => $i->code, 'penyebab' => Str::limit((string) $i->cause, 160)])->values()->all()],
            'insights' => array_values(array_filter([$open->count() ? "{$open->count()} insiden masih terbuka." : null, $byUnit ? 'Unit dengan insiden terbanyak: ' . array_key_first($byUnit) . ' (' . reset($byUnit) . ').' : null, $inc->whereNull('risk_id')->count() ? $inc->whereNull('risk_id')->count() . ' insiden belum ditautkan ke risiko di register.' : null])),
            'recs' => ['Tautkan setiap insiden ke risiko terkait dan nilai ulang skor kemungkinan bila insiden berulang.', 'Tutup insiden setelah tindakan korektif terverifikasi; catat lesson learned dan improvement.', 'Bandingkan kerugian dengan risk limit kategori pada laporan triwulanan.'],
            'refs' => $this->refs($inc, '/incidents/')];
    }

    private function incident(?int $id): array
    {
        $i = Incident::with('risk')->findOrFail((int) $id);
        $this->authorizeView($i);
        $facts = ['kode' => $i->code, 'judul' => $i->title, 'tanggal' => $i->occurred_at?->format('Y-m-d H:i'), 'lokasi' => $i->location, 'unit' => $this->data->unitName($i->unit_id), 'status' => config('manrisk.incident_statuses')[$i->status] ?? $i->status,
            'kronologi' => Str::limit((string) $i->chronology, 800), 'penyebab' => Str::limit((string) $i->cause, 500), 'dampak' => Str::limit((string) $i->impact, 500), 'respons' => Str::limit((string) $i->response, 400), 'tindakan_korektif' => Str::limit((string) $i->corrective_action, 400),
            'kerugian' => (float) $i->loss_amount, 'jenis_kerugian' => $i->loss_type, 'risiko_terkait' => $i->risk ? ['kode' => $i->risk->code, 'nama' => $i->risk->name, 'skor_residual' => $i->risk->residual_score] : null];
        $similar = $i->risk_id ? Incident::where('risk_id', $i->risk_id)->where('id', '!=', $i->id)->count() : 0;
        return ['halaman' => "Insiden {$i->code}", 'facts' => $facts + ['insiden_lain_pada_risiko_sama' => $similar], 'lists' => [],
            'insights' => array_values(array_filter([!$i->risk_id ? 'Insiden belum ditautkan ke risiko.' : null, $similar ? "Terdapat {$similar} insiden lain pada risiko yang sama — indikasi kontrol belum efektif." : null, !$i->corrective_action ? 'Tindakan korektif belum dicatat.' : null])),
            'recs' => ['rootcause' => ['Mengapa 1: apa yang langsung memicu kejadian? (' . Str::limit((string) $i->cause ?: 'penyebab belum diisi', 80) . ')', 'Mengapa 2–3: kontrol apa yang seharusnya mencegah/mendeteksi dan mengapa gagal?', 'Mengapa 4–5: faktor proses, orang, teknologi, atau pihak ketiga yang mendasarinya.', 'Tetapkan tindakan pencegahan pada akar penyebab, bukan hanya gejala.'],
                'lessons' => ['Dokumentasikan kronologi, penyebab, dan respons sebagai lesson learned.', 'Bagikan ke unit dengan proses serupa dan perbarui SOP/kontrol terkait.', $i->risk_id ? 'Nilai ulang kemungkinan risiko ' . $i->risk?->code . ' pada review berikutnya.' : 'Buat atau tautkan risiko di register untuk insiden ini.']],
            'refs' => [$i->code => "/incidents/{$i->id}"] + ($i->risk ? [$i->risk->code => "/risks/{$i->risk->id}"] : [])];
    }

    private function losses(): array
    {
        $l = $this->data->losses()->get();
        $byYear = $l->groupBy('year')->map(fn ($g) => (float) $g->sum('amount'))->sortKeys()->all();
        $byCat = $l->groupBy(fn ($x) => $this->data->categoryName($x->category_id))->map(fn ($g) => (float) $g->sum('amount'))->sortDesc()->take(8)->all();
        $years = array_keys($byYear);
        $ins = [];
        if (count($years) >= 2) {
            $a = $byYear[$years[count($years) - 2]];
            $b = $byYear[end($years)];
            $ins[] = 'Kerugian ' . end($years) . ' ' . ($b > $a ? 'naik' : 'turun') . ' dibanding ' . $years[count($years) - 2] . ' (Rp ' . number_format($a, 0, ',', '.') . ' → Rp ' . number_format($b, 0, ',', '.') . ').';
        }
        if ($byCat) {
            $ins[] = 'Kategori dengan kerugian terbesar: ' . array_key_first($byCat) . '.';
        }
        return ['halaman' => 'Loss Event Database', 'facts' => ['jumlah_kejadian' => $l->count(), 'total_kerugian' => (float) $l->sum('amount')], 'lists' => ['per_tahun' => $byYear, 'per_kategori' => $byCat,
            'kejadian_terbesar' => $l->sortByDesc('amount')->take(8)->map(fn ($x) => ['tahun' => $x->year, 'peristiwa' => Str::limit($x->event, 80), 'risiko' => Str::limit($x->risk_name, 60), 'nilai' => (float) $x->amount])->values()->all()],
            'insights' => $ins, 'recs' => ['Gunakan data kerugian historis untuk mengkalibrasi skala dampak dan eksposur risiko.', 'Prioritaskan pengendalian pada kategori dengan kerugian terbesar dan berulang.'], 'refs' => []];
    }

    private function kris(): array
    {
        $kris = $this->data->kris()->where('active', true)->with(['risk:id,code', 'values' => fn ($q) => $q->orderByDesc('period')->limit(6)])->get();
        $breach = $kris->whereIn('status', ['warning', 'critical'])->sortBy(fn ($k) => $k->status === 'critical' ? 0 : 1);
        $row = fn ($k) => ['kode' => $k->code, 'nama' => Str::limit($k->name, 80), 'risiko' => $k->risk?->code, 'status' => Datasets::KRI_STATUSES[$k->status] ?? $k->status, 'nilai_terakhir' => $k->last_value !== null ? (float) $k->last_value : null,
            'ambang_waspada' => (float) $k->threshold_warn, 'ambang_kritis' => (float) $k->threshold_crit, 'arah' => $k->direction === 'down_bad' ? 'turun buruk' : 'naik buruk', 'riwayat' => $k->values->sortBy('period')->map(fn ($v) => (float) $v->value)->values()->all()];
        $stale = $kris->filter(fn ($k) => $k->values->isEmpty() || Datasets::date($k->values->first()->period)->lt(now()->subMonths(2)));
        return ['halaman' => 'KRI & early warning', 'facts' => ['kri_aktif' => $kris->count(), 'per_status' => $this->countBy($kris, fn ($k) => Datasets::KRI_STATUSES[$k->status] ?? $k->status), 'belum_diisi_2_bulan' => $stale->count()],
            'lists' => ['melewati_ambang' => $breach->take(10)->map($row)->values()->all(), 'semua_kri' => $kris->take(20)->map($row)->values()->all()],
            'insights' => array_values(array_filter([$breach->where('status', 'critical')->count() ? $breach->where('status', 'critical')->count() . ' KRI berstatus kritis: ' . $breach->where('status', 'critical')->take(4)->pluck('code')->implode(', ') . '.' : null, $breach->where('status', 'warning')->count() ? $breach->where('status', 'warning')->count() . ' KRI waspada.' : null, $stale->count() ? "{$stale->count()} KRI belum diisi nilainya dalam 2 bulan terakhir." : null])),
            'recs' => ['breach' => ['Pastikan setiap KRI kritis memiliki improvement/action plan aktif pada risiko terkaitnya.', 'Nilai ulang kemungkinan risiko terkait bila KRI kritis bertahan dua periode.'], 'thresholds' => ['Bandingkan ambang dengan rata-rata 6 periode terakhir; ambang yang tidak pernah terlampaui mungkin terlalu longgar.', 'Selaraskan ambang kritis dengan toleransi kategori risiko.'],
                'summary' => ['Isi nilai KRI sesuai frekuensi agar peringatan dini bekerja.']],
            'refs' => $this->refs($kris->pluck('risk')->filter(), '/risks/')];
    }

    private function alerts(): array
    {
        $a = UnitScope::morph(Alert::query(), $this->data->user())->whereNull('handled_at')->latest()->limit(200)->get(['id', 'type', 'severity', 'title', 'created_at', 'read_at']);
        $sev = ['critical' => 'Kritis', 'warning' => 'Peringatan', 'info' => 'Info'];
        return ['halaman' => 'Peringatan', 'facts' => ['belum_ditangani' => $a->count(), 'belum_dibaca' => $a->whereNull('read_at')->count(), 'per_tingkat' => $this->countBy($a, fn ($x) => $sev[$x->severity] ?? $x->severity), 'per_jenis' => $this->countBy($a, fn ($x) => $x->type)],
            'lists' => ['terbaru' => $a->sortBy(fn ($x) => ['critical' => 0, 'warning' => 1][$x->severity] ?? 2)->take(12)->map(fn ($x) => ['tingkat' => $sev[$x->severity] ?? $x->severity, 'judul' => Str::limit($x->title, 100), 'tanggal' => $x->created_at?->format('Y-m-d')])->values()->all()],
            'insights' => [$a->where('severity', 'critical')->count() . ' peringatan kritis belum ditangani.'], 'recs' => ['Tangani peringatan kritis lebih dulu, lalu tandai ditangani agar tidak muncul ulang.', 'Peringatan berulang dari jenis yang sama menandakan perlunya perbaikan proses.'], 'refs' => []];
    }

    private function objectives(): array
    {
        $ids = $this->data->user()->accessibleUnitIds();
        $obj = Objective::where('active', true)->with(['risks' => fn ($q) => $q->where('status', '!=', 'closed')->when($ids !== null, fn ($x) => $x->whereIn('unit_id', $ids))->select('id', 'code', 'objective_id', 'residual_score', 'residual_level')])->get();
        $rows = $obj->map(fn ($o) => ['kode' => $o->code, 'sasaran' => Str::limit($o->name, 90), 'kpi' => $o->kpi, 'jumlah_risiko' => $o->risks->count(), 'skor_tertinggi' => $o->risks->max('residual_score'), 'risiko_tinggi' => $o->risks->whereIn('residual_level', ['high', 'very_high'])->count()])->sortByDesc('skor_tertinggi')->values();
        $empty = $rows->where('jumlah_risiko', 0);
        return ['halaman' => 'Pemetaan sasaran', 'facts' => ['sasaran_aktif' => $obj->count(), 'sasaran_tanpa_risiko' => $empty->count()], 'lists' => ['sasaran' => $rows->take(15)->all()],
            'insights' => array_values(array_filter([$rows->first() && $rows->first()['jumlah_risiko'] ? "Sasaran paling berisiko: {$rows->first()['kode']} (skor tertinggi {$rows->first()['skor_tertinggi']})." : null, $empty->count() ? "{$empty->count()} sasaran belum dipetakan risikonya." : null])),
            'recs' => ['Petakan risiko untuk setiap sasaran strategis agar ancaman terhadap pencapaian KPI terlihat.', 'Laporkan sasaran dengan risiko tinggi pada rapat kinerja.'], 'refs' => []];
    }

    private function units(): array
    {
        $risks = $this->data->risks()->where('status', '!=', 'closed')->get(['id', 'unit_id', 'residual_score', 'residual_level']);
        $plans = $this->openPlans()->filter(fn ($p) => $p->computedStatus() === 'overdue');
        $rows = $risks->groupBy('unit_id')->map(fn ($g, $u) => ['unit' => $this->data->unitName($u), 'jumlah_risiko' => $g->count(), 'risiko_tinggi' => $g->whereIn('residual_level', ['high', 'very_high'])->count(), 'rata_skor' => round((float) $g->avg('residual_score'), 1),
            'action_plan_terlambat' => $plans->filter(fn ($p) => ($p->unit_id ?? $p->risk?->unit_id) == $u)->count()])->sortByDesc('risiko_tinggi')->values();
        return ['halaman' => 'Struktur organisasi', 'facts' => ['unit_dengan_risiko' => $rows->count()], 'lists' => ['profil_unit' => $rows->take(20)->all()],
            'insights' => $rows->first() ? ["Unit dengan risiko tinggi terbanyak: {$rows->first()['unit']} ({$rows->first()['risiko_tinggi']})."] : [],
            'recs' => ['Pastikan setiap unit memiliki Risk Officer dan Risk Owner aktif.', 'Jadwalkan pendampingan untuk unit dengan risiko tinggi dan action plan terlambat terbanyak.'], 'refs' => []];
    }

    private function framework(): array
    {
        $items = FrameworkItem::orderBy('clause')->get();
        $st = ['met' => 'Terpenuhi', 'partial' => 'Sebagian', 'unmet' => 'Belum'];
        $gaps = $items->where('status', '!=', 'met')->sortBy(fn ($i) => $i->status === 'unmet' ? 0 : 1);
        return ['halaman' => 'Kerangka ISO 31000', 'facts' => ['butir' => $items->count(), 'per_status' => $this->countBy($items, fn ($i) => $st[$i->status] ?? $i->status), 'kepatuhan_persen' => $items->count() ? round($items->where('status', 'met')->count() / $items->count() * 100) : null],
            'lists' => ['kesenjangan' => $gaps->take(15)->map(fn ($i) => ['klausul' => $i->clause, 'kelompok' => $i->group, 'judul' => $i->title, 'status' => $st[$i->status] ?? $i->status, 'modul' => $i->module, 'catatan' => Str::limit((string) $i->note, 120)])->values()->all()],
            'insights' => [$gaps->where('status', 'unmet')->count() . ' butir belum terpenuhi dan ' . $gaps->where('status', 'partial')->count() . ' sebagian.'],
            'recs' => ['Dahulukan butir kerangka (klausul 5) karena menjadi dasar proses.', 'Tautkan setiap butir ke bukti di menu Dokumen & Bukti sebelum diubah menjadi Terpenuhi.'], 'refs' => []];
    }

    private function rmi(): array
    {
        $a = RmiAssessment::orderByDesc('year')->limit(10)->get();
        $phases = config('manrisk.rmi.phases');
        $last = $a->where('status', 'final')->first();
        return ['halaman' => 'Risk Maturity Index', 'facts' => ['penilaian' => $a->count(), 'indeks_final_terakhir' => $last?->score, 'fase' => $last?->phase ? ($phases[$last->phase]['label'] ?? $last->phase) : null, 'internal_tahun_ini' => $a->where('year', now()->year)->where('type', 'internal')->count() > 0,
            'independen_3_tahun_terakhir' => $a->where('type', 'independent')->where('year', '>=', now()->year - 2)->count() > 0],
            'lists' => ['riwayat' => $a->map(fn ($x) => ['tahun' => $x->year, 'jenis' => $x->type === 'independent' ? 'Independen' : 'Internal', 'status' => $x->status, 'indeks' => $x->score, 'fase' => $x->phase ? ($phases[$x->phase]['label'] ?? $x->phase) : null])->values()->all(), 'batas_fase' => collect($phases)->map(fn ($p) => $p['label'] . ' ≤ ' . $p['max'])->values()->all()],
            'insights' => array_values(array_filter([$last ? "Indeks final terakhir {$last->score} ({$last->year})." : 'Belum ada penilaian RMI final.', !$a->where('year', now()->year)->where('type', 'internal')->count() ? 'Penilaian internal tahun berjalan belum dibuat (wajib setiap tahun).' : null, !$a->where('type', 'independent')->where('year', '>=', now()->year - 2)->count() ? 'Penilaian independen belum dilakukan dalam 3 tahun terakhir.' : null])),
            'recs' => ['improve' => ['Fokus pada dimensi dengan skor terendah dan tetapkan target fase tahun depan.', 'Lengkapi bukti untuk setiap parameter agar skor dapat dipertanggungjawabkan saat penilaian independen.']], 'refs' => []];
    }

    private function rmiAssessment(?int $id): array
    {
        $a = RmiAssessment::with('scores.parameter')->findOrFail((int) $id);
        $this->authorizeView($a);
        $dims = config('manrisk.rmi.dimensions');
        $byDim = $a->scores->filter(fn ($s) => $s->score)->groupBy(fn ($s) => $s->parameter?->dimension)->map(fn ($g, $d) => ['dimensi' => $dims[$d] ?? $d, 'rata_skor' => round((float) $g->avg('score'), 2), 'parameter' => $g->count()])->sortBy('rata_skor')->values();
        $low = $a->scores->filter(fn ($s) => $s->score && $s->score <= 2)->map(fn ($s) => ['kode' => $s->parameter?->code, 'parameter' => Str::limit((string) $s->parameter?->name, 90), 'skor' => $s->score, 'bukti' => $s->evidence ? 'ada' : 'belum'])->values();
        $missing = $a->scores->whereNull('score')->count();
        return ['halaman' => "Penilaian RMI {$a->year}", 'facts' => ['tahun' => $a->year, 'jenis' => $a->type, 'status' => $a->status, 'indeks' => $a->score, 'parameter_belum_dinilai' => $missing], 'lists' => ['per_dimensi' => $byDim->all(), 'parameter_skor_rendah' => $low->take(12)->all()],
            'insights' => array_values(array_filter([$byDim->first() ? "Dimensi terendah: {$byDim->first()['dimensi']} ({$byDim->first()['rata_skor']})." : null, $missing ? "{$missing} parameter belum dinilai." : null])),
            'recs' => ['improve' => ['Susun rencana peningkatan untuk parameter dengan skor ≤ 2 beserta PIC dan target tahun depan.', 'Lengkapi bukti untuk setiap skor sebelum memfinalkan penilaian.']], 'refs' => []];
    }

    private function approvals(): array
    {
        $ap = $this->data->approvals()->with('steps')->latest()->limit(300)->get();
        $pending = $ap->where('status', 'pending');
        $late = $pending->filter(fn ($x) => ($s = $x->steps->firstWhere('step_no', $x->current_step)) && $s->due_at && Datasets::date($s->due_at)->lt(now()));
        return ['halaman' => 'Persetujuan', 'facts' => ['menunggu' => $pending->count(), 'lewat_sla' => $late->count(), 'per_jenis_menunggu' => $this->countBy($pending, fn ($x) => Datasets::APPROVAL_TYPES[$x->type] ?? $x->type), 'rata_lama_menunggu_hari' => round((float) $pending->avg(fn ($x) => $x->created_at->diffInDays(now())), 1)],
            'lists' => ['menunggu_terlama' => $pending->sortBy('created_at')->take(10)->map(fn ($x) => ['kode' => $x->code, 'jenis' => Datasets::APPROVAL_TYPES[$x->type] ?? $x->type, 'langkah' => "{$x->current_step}/{$x->total_steps}", 'diajukan' => $x->created_at?->format('Y-m-d')])->values()->all()],
            'insights' => array_values(array_filter([$late->count() ? "{$late->count()} pengajuan melewati SLA persetujuan." : null, $pending->count() ? "{$pending->count()} pengajuan menunggu keputusan." : 'Tidak ada pengajuan yang menunggu.'])),
            'recs' => ['Putuskan pengajuan yang melewati SLA lebih dulu; gunakan Minta revisi bila data belum lengkap.', 'Perubahan skor dan penerimaan risiko berdampak langsung pada profil risiko — prioritaskan jenis tersebut.'], 'refs' => []];
    }

    private function documents(): array
    {
        $docs = UnitScope::morph(Document::query(), $this->data->user())->get(['id', 'type', 'title', 'status', 'expires_at', 'subject_type']);
        $exp = $docs->filter(fn ($d) => $d->expires_at && Datasets::date($d->expires_at)->lt(now()->addDays(60)))->sortBy('expires_at');
        $types = config('manrisk.document_types');
        return ['halaman' => 'Dokumen & bukti', 'facts' => ['dokumen' => $docs->count(), 'per_jenis' => $this->countBy($docs, fn ($d) => $types[$d->type] ?? $d->type), 'tanpa_tautan' => $docs->whereNull('subject_type')->count(), 'kedaluwarsa_atau_60_hari' => $exp->count()],
            'lists' => ['kedaluwarsa' => $exp->take(10)->map(fn ($d) => ['judul' => Str::limit($d->title, 80), 'jenis' => $types[$d->type] ?? $d->type, 'kedaluwarsa' => Datasets::date($d->expires_at)->format('Y-m-d')])->values()->all()],
            'insights' => array_values(array_filter([$exp->count() ? "{$exp->count()} dokumen kedaluwarsa atau akan kedaluwarsa dalam 60 hari." : null, $docs->whereNull('subject_type')->count() ? $docs->whereNull('subject_type')->count() . ' dokumen belum ditautkan ke objek (risiko, kontrol, action plan).' : null])),
            'recs' => ['Perbarui dokumen yang akan kedaluwarsa dengan versi baru (riwayat versi tersimpan).', 'Tautkan bukti ke objeknya agar mudah ditelusuri auditor.'], 'refs' => []];
    }

    private function audit(): array
    {
        $logs = AuditLog::where('organization_id', $this->data->user()->organization_id)->where('created_at', '>=', now()->subDays(30))->get(['action', 'subject_type', 'subject_label', 'user_id', 'created_at']);
        $del = $logs->where('action', 'deleted');
        $perDay = $logs->groupBy(fn ($l) => $l->created_at->format('Y-m-d'))->map->count();
        $peak = $perDay->sortDesc()->keys()->first();
        return ['halaman' => 'Audit trail', 'facts' => ['aktivitas_30_hari' => $logs->count(), 'per_aksi' => $this->countBy($logs, fn ($l) => $l->action), 'per_objek' => $this->countBy($logs, fn ($l) => $l->subject_type, 8), 'penghapusan' => $del->count(), 'pengguna_aktif' => $logs->pluck('user_id')->filter()->unique()->count(), 'hari_tersibuk' => $peak ? "$peak ({$perDay[$peak]})" : null],
            'lists' => ['penghapusan_terbaru' => $del->sortByDesc('created_at')->take(10)->map(fn ($l) => ['objek' => $l->subject_type, 'label' => $l->subject_label, 'tanggal' => $l->created_at->format('Y-m-d H:i')])->values()->all()],
            'insights' => array_values(array_filter([$del->count() ? "{$del->count()} penghapusan dalam 30 hari." : null, $peak ? "Aktivitas tertinggi pada {$peak}." : null])),
            'recs' => ['anomaly' => ['Periksa penghapusan objek penting (risiko, kontrol, dokumen) dan pastikan ada dasar persetujuannya.', 'Perhatikan perubahan massal di luar jam kerja atau oleh satu akun dalam waktu singkat.'], 'summary' => ['Ekspor audit trail periode tertentu untuk lini ketiga bila diperlukan.']], 'refs' => []];
    }

    private function users(): array
    {
        $u = User::get(['id', 'role', 'active', 'mfa_enabled', 'last_login_at', 'unit_id']);
        $roles = User::ROLES;
        $stale = $u->where('active', true)->filter(fn ($x) => !$x->last_login_at || Datasets::date($x->last_login_at)->lt(now()->subDays(90)));
        return ['halaman' => 'Pengguna & akun', 'facts' => ['akun' => $u->count(), 'aktif' => $u->where('active', true)->count(), 'mfa_aktif' => $u->where('mfa_enabled', true)->count(), 'per_peran' => $this->countBy($u, fn ($x) => $roles[$x->role] ?? $x->role), 'tidak_login_90_hari' => $stale->count(),
            'admin_tanpa_mfa' => $u->where('active', true)->whereIn('role', ['super_admin', 'risk_admin'])->where('mfa_enabled', false)->count()], 'lists' => [],
            'insights' => array_values(array_filter([$u->count() ? round($u->where('mfa_enabled', true)->count() / max(1, $u->count()) * 100) . '% akun sudah mengaktifkan MFA.' : null, $stale->count() ? "{$stale->count()} akun aktif tidak login lebih dari 90 hari." : null])),
            'recs' => ['security' => ['Wajibkan MFA untuk Super Admin dan Risk Administrator.', 'Nonaktifkan akun yang tidak login lebih dari 90 hari setelah konfirmasi atasan.', 'Tinjau hak akses per peran setiap semester.']], 'refs' => []];
    }

    private function settings(): array
    {
        $org = $this->data->user()->organization;
        $s = (array) ($org?->settings ?? []);
        return ['halaman' => 'Pengaturan organisasi', 'facts' => ['organisasi' => $org?->name, 'pengaturan' => collect($s)->except(['smtp', 'mail'])->map(fn ($v) => is_scalar($v) ? $v : json_encode($v))->take(30)->all()], 'lists' => [], 'insights' => [],
            'recs' => ['security' => ['Gunakan panjang sandi minimal 10–12 karakter dan riwayat sandi ≥ 5.', 'Batasi masa sesi tidak aktif 15–30 menit.', 'Aktifkan MFA untuk peran administrator.'], 'summary' => ['Pengaturan ini memengaruhi seluruh pengguna organisasi; perubahan tercatat di audit trail.']], 'refs' => []];
    }

    private function authorizeView($model): void
    {
        if (!$this->data->user()->can('view', $model)) {
            throw new AuthorizationException('Objek di luar cakupan akses Anda.');
        }
    }

    // ---------- jawaban lokal (tanpa penyedia AI) ----------

    private function local(array $snap, ?string $action, ?string $question, string $label): string
    {
        $out = [];
        $title = $snap['halaman'] ?? $label;
        $actionLabel = null;
        foreach (self::MODULES as $m) {
            if ($action && isset($m[1][$action]) && ($m[0] === $label)) {
                $actionLabel = $m[1][$action];
            }
        }
        $out[] = '**' . ($actionLabel ?: 'Analisis') . " · {$title}**";
        if ($question) {
            $out[] = '_Mode lokal (tanpa kunci API): pertanyaan bebas dijawab dengan ringkasan data halaman ini. Aktifkan kunci API untuk jawaban percakapan penuh._';
        }
        $facts = collect($snap['facts'] ?? [])->filter(fn ($v) => $v !== null && $v !== [] && !is_array($v) || (is_array($v) && $v && !array_is_list($v) && count($v) <= 8));
        if ($facts->count()) {
            $out[] = "\n### Data utama";
            foreach ($facts->take(12) as $k => $v) {
                $out[] = '- ' . $this->labelOf($k) . ': ' . $this->val($k, $v);
            }
        }
        if (!empty($snap['insights'])) {
            $out[] = "\n### Temuan";
            foreach ($snap['insights'] as $i) {
                $out[] = "- {$i}";
            }
        }
        $lists = $snap['lists'] ?? [];
        $listKey = match ($action) {
            'priority' => 'risiko_teratas', 'changes' => 'risiko_skor_naik', 'overdue' => 'terlambat', 'weak' => 'kontrol_lemah', 'gaps' => isset($lists['risiko_tanpa_kontrol']) ? 'risiko_tanpa_kontrol' : 'sasaran',
            'breach' => 'melewati_ambang', 'expiring' => 'kedaluwarsa', 'loss' => isset($lists['kejadian_terbesar']) ? 'kejadian_terbesar' : 'kerugian_terbesar', 'limit' => 'kategori', 'rootcause' => 'penyebab_terbaru',
            'improve' => isset($lists['parameter_skor_rendah']) ? 'parameter_skor_rendah' : 'riwayat', 'budget' => 'anggaran_terbesar', 'anomaly' => 'penghapusan_terbaru', default => null,
        };
        $listKey = $listKey && !empty($lists[$listKey]) ? $listKey : collect($lists)->filter(fn ($l) => is_array($l) && $l && array_is_list($l))->keys()->first();
        if ($listKey && !empty($lists[$listKey]) && array_is_list($lists[$listKey])) {
            $out[] = "\n### " . $this->labelOf($listKey);
            foreach (array_slice($lists[$listKey], 0, 8) as $row) {
                $out[] = '- ' . (is_array($row) ? collect($row)->filter(fn ($v) => $v !== null && $v !== '' && !is_array($v))->map(fn ($v, $k) => is_numeric($v) ? $this->labelOf($k) . ' ' . $this->val($k, $v) : $v)->implode(' · ') : $row);
            }
        }
        $recs = $snap['recs'] ?? [];
        $recList = array_is_list($recs) ? $recs : ($recs[$action] ?? $recs['summary'] ?? (array_values($recs)[0] ?? []));
        if ($recList) {
            $out[] = "\n### " . (['explain' => 'Penjelasan', 'guide' => 'Panduan', 'statement' => 'Contoh', 'test' => 'Rencana uji', 'rootcause' => 'Analisis akar penyebab', 'kri' => 'Usulan KRI', 'controls' => 'Usulan kontrol'][$action] ?? 'Rekomendasi');
            foreach ($recList as $i => $r) {
                $out[] = ($i + 1) . ". {$r}";
            }
        }
        if ($action === 'help' || ($question && preg_match('/dashboard|grafik|chart|visual/i', $question))) {
            $out[] = "\nUntuk grafik dan dashboard sesuai kebutuhan, buka **AI Analitik** lalu tulis permintaan Anda (mis. \"jumlah risiko per unit dan level\").";
        }
        $out[] = "\n_Saran AI bersifat pendukung; keputusan tetap pada pemilik risiko._";
        return implode("\n", $out);
    }

    private function labelOf(string $k): string
    {
        return ucfirst(str_replace('_', ' ', $k));
    }

    private function val(string $k, $v): string
    {
        if (is_bool($v)) {
            return $v ? 'ya' : 'belum';
        }
        if (is_array($v)) {
            return collect($v)->map(fn ($n, $l) => "{$l} {$n}")->implode(', ');
        }
        if (is_numeric($v) && (preg_match('/eksposur|kerugian|anggaran|realisasi/', $k) || in_array($k, ['nilai', 'nilai_transaksi'], true))) {
            return 'Rp ' . number_format((float) $v, 0, ',', '.');
        }
        if (is_numeric($v) && str_contains($k, 'persen')) {
            return $v . '%';
        }
        return (string) $v;
    }
}

<?php

namespace Database\Seeders;

use App\Models\ActionPlan;
use App\Models\ActionProgress;
use App\Models\Approval;
use App\Models\ApprovalStep;
use App\Models\Consultation;
use App\Models\ContextFactor;
use App\Models\Control;
use App\Models\ControlAssessment;
use App\Models\CriteriaVersion;
use App\Models\Document;
use App\Models\Improvement;
use App\Models\Incident;
use App\Models\Kri;
use App\Models\KriValue;
use App\Models\Lesson;
use App\Models\LossEvent;
use App\Models\Objective;
use App\Models\Organization;
use App\Models\OrgUnit;
use App\Models\Process;
use App\Models\Program;
use App\Models\Review;
use App\Models\Risk;
use App\Models\RiskCategory;
use App\Models\RiskSnapshot;
use App\Models\RiskVersion;
use App\Models\Scope;
use App\Models\User;
use App\Support\Scoring;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/** Data demo fiktif (dipindahkan dari purwarupa): 127 risiko lengkap dengan turunannya. */
class DemoSeeder extends Seeder
{
    private array $d;
    private Organization $org;
    private array $units = [];      // nama unit → id
    private array $cats = [];       // nama kategori → id
    private array $users = [];      // kode PEOPLE → User
    private array $objectives = []; // SS-1 → id
    private array $processes = [];  // nama → id
    private array $risks = [];      // R-001 → Risk
    private array $controls = [];   // C-01 → Control
    private Scoring $scoring;
    private int $seed = 31000;

    public function run(): void
    {
        $this->d = json_decode(file_get_contents(__DIR__ . '/data/demo.json'), true);
        $this->org = Organization::firstOrFail();
        if (Risk::withoutGlobalScopes()->where('organization_id', $this->org->id)->exists()) {
            $this->command?->warn('Data demo sudah ada, dilewati.');
            return;
        }
        $admin = User::withoutGlobalScopes()->where('organization_id', $this->org->id)->where('role', 'super_admin')->first();
        auth()->login($admin); // agar organization_id & audit terisi otomatis
        $this->scoring = new Scoring(CriteriaVersion::current());
        $this->cats = RiskCategory::pluck('id', 'name')->all();

        $this->seedUnits();
        $this->seedPeople();
        $this->seedObjectives();
        $this->seedContext();
        $this->seedRisks();
        $this->seedControls();
        $this->seedActions();
        $this->seedKris();
        $this->seedIncidents();
        $this->seedReviews();
        $this->seedDocuments();
        $this->seedImprovements();
        $this->seedApprovals();
        $this->seedFrameworkScores();
        auth()->logout();
    }

    /** Buat record dengan created_at tertentu (kolom timestamp tidak fillable). */
    private function mk(string $class, array $attrs)
    {
        $created = $attrs['created_at'] ?? null;
        unset($attrs['created_at']);
        $m = $class::create($attrs);
        if ($created) {
            $m->forceFill(['created_at' => $created, 'updated_at' => $created])->saveQuietly();
        }
        return $m;
    }

    private function rnd(): float
    {
        $this->seed = ($this->seed * 1103515245 + 12345) % 2147483648;
        return $this->seed / 2147483648;
    }

    private function between(int $a, int $b): int
    {
        return $a + (int) floor($this->rnd() * ($b - $a + 1));
    }

    private function seedUnits(): void
    {
        $typeMap = ['Organisasi' => 'organization', 'Unit Eselon I' => 'deputy', 'Direktorat' => 'directorate', 'Biro' => 'bureau', 'Bagian' => 'division', 'Subdirektorat' => 'subdivision', 'Unit Pengawasan' => 'unit'];
        $skip = ['Program', 'Kegiatan', 'Proses Bisnis'];
        $n = 0;
        $walk = function (array $node, ?OrgUnit $parent, int $sort) use (&$walk, &$n, $typeMap, $skip) {
            if (in_array($node['t'], $skip, true)) {
                return;
            }
            if ($node['t'] === 'Organisasi') {
                foreach ($node['k'] ?? [] as $i => $child) {
                    $walk($child, null, $i);
                }
                return;
            }
            $n++;
            $code = strtoupper(preg_replace('/[^A-Z]/', '', implode('', array_map(fn ($w) => mb_substr($w, 0, 1), explode(' ', str_replace('&', '', $node['n'])))))) . '-' . str_pad((string) $n, 2, '0', STR_PAD_LEFT);
            $unit = OrgUnit::create(['parent_id' => $parent?->id, 'name' => $node['n'], 'code' => $code, 'type' => $typeMap[$node['t']] ?? 'unit', 'sort' => $sort]);
            $unit->refreshPath();
            $this->units[$node['n']] = $unit->id;
            foreach ($node['k'] ?? [] as $i => $child) {
                $walk($child, $unit, $i);
            }
        };
        $walk($this->d['ORG_TREE'], null, 0);
        $umr = OrgUnit::create(['name' => 'Unit Manajemen Risiko', 'code' => 'UMR-99', 'type' => 'unit', 'sort' => 99]);
        $umr->refreshPath();
        $this->units['Unit Manajemen Risiko'] = $umr->id;
        // Akun peran bawaan yang bercakupan unit ditempatkan di Direktorat TI; manajer di UMR
        User::whereIn('role', ['risk_officer', 'risk_owner'])->whereNull('unit_id')->update(['unit_id' => $this->units['Direktorat Teknologi Informasi']]);
        User::whereIn('role', ['risk_manager', 'risk_admin'])->whereNull('unit_id')->update(['unit_id' => $umr->id]);
    }

    private function unitId(int $idx): int
    {
        return $this->units[$this->d['UNITS'][$idx]];
    }

    private function seedPeople(): void
    {
        $owners = ['ti', 'keu', 'yan', 'um', 'sdm', 'hk', 'ren', 'ins'];
        foreach ($this->d['PEOPLE'] as $key => $p) {
            $idx = array_search($key, $owners, true);
            $unitId = $this->unitId($idx);
            $email = strtolower(str_replace(' ', '.', $p['n'])) . '@bldn.go.id';
            $user = User::create(['name' => $p['n'], 'email' => $email, 'password' => env('MR_SEED_PASSWORD', 'ManRisk#2026'), 'role' => 'risk_owner', 'position' => $p['j'], 'unit_id' => $unitId, 'active' => true, 'password_changed_at' => now()]);
            $this->users[$key] = $user;
            OrgUnit::where('id', $unitId)->update(['head_user_id' => $user->id]);
        }
        // Akun peran bawaan: Risk Owner/Officer bawaan dicakupkan ke Direktorat TI (sudah di UserSeeder)
        $roleMap = ['Risk Officer' => 'risk_officer', 'Risk Owner' => 'risk_owner', 'Risk Manager' => 'risk_manager', 'Super Admin' => 'super_admin', 'Management' => 'management', 'Auditor' => 'auditor', 'Risk Administrator' => 'risk_admin'];
        foreach ($this->d['USERS'] as $u) {
            if (User::where('email', $u['e'])->exists() || in_array($u['n'], array_column($this->d['PEOPLE'], 'n'), true)) {
                continue;
            }
            User::create(['name' => $u['n'], 'email' => $u['e'], 'password' => env('MR_SEED_PASSWORD', 'ManRisk#2026'), 'role' => $roleMap[$u['role']] ?? 'risk_officer', 'unit_id' => $this->units[$u['unit']] ?? null, 'active' => $this->rnd() > 0.1, 'password_changed_at' => now()->subDays($this->between(1, 90))]);
        }
    }

    private function seedObjectives(): void
    {
        foreach ($this->d['OBJECTIVES'] as $i => $o) {
            $this->objectives[$o['id']] = Objective::create(['code' => $o['id'], 'name' => $o['n'], 'kpi' => $o['ik'], 'period' => '2026', 'sort' => $i + 1])->id;
        }
        $programs = [
            ['Program Transformasi Digital', 'SS-1', 'Direktorat Teknologi Informasi', 12500], ['Program Penguatan Tata Kelola Keuangan', 'SS-2', 'Biro Keuangan', 3200],
            ['Program Peningkatan Kualitas Layanan', 'SS-1', 'Direktorat Pelayanan Publik', 5400], ['Program Keamanan Informasi', 'SS-3', 'Direktorat Teknologi Informasi', 4100],
            ['Program Pengembangan SDM', 'SS-4', 'Biro SDM', 2100],
        ];
        $progIds = [];
        foreach ($programs as [$name, $obj, $unit, $budget]) {
            $progIds[$name] = Program::create(['name' => $name, 'objective_id' => $this->objectives[$obj], 'unit_id' => $this->units[$unit] ?? null, 'budget' => $budget * 1e6])->id;
        }
        $procNames = array_values(array_unique(array_column($this->d['RISKS'], 'proc')));
        foreach ($procNames as $name) {
            $risk = collect($this->d['RISKS'])->firstWhere('proc', $name);
            $this->processes[$name] = Process::create(['name' => $name, 'unit_id' => $this->unitId($risk['unit']), 'program_id' => array_values($progIds)[$risk['unit'] % count($progIds)]])->id;
        }
    }

    private function seedContext(): void
    {
        $scope = Scope::create(['name' => 'Penilaian Risiko Organisasi Tahun 2026', 'objective' => 'Memetakan dan mengelola risiko yang dapat menghambat pencapaian sasaran strategis 2026.', 'boundaries' => 'Seluruh unit kerja eselon I dan II; tidak termasuk unit pelaksana teknis daerah.', 'period' => 'Jan–Des 2026', 'area' => 'Layanan publik digital, keuangan, SDM, hukum, TI, pengadaan, perencanaan, pengawasan.', 'created_by' => auth()->id()]);
        $factors = [
            ['internal', 'SDM', 'Kompetensi digital pegawai belum merata; 35% pegawai belum tersertifikasi.', 'weakness'], ['internal', 'Infrastruktur TI', 'Pusat data utama tanpa redundansi penuh; DRC dalam pembangunan.', 'weakness'],
            ['internal', 'Tata Kelola', 'Komitmen pimpinan tinggi; kebijakan MR telah ditetapkan.', 'strength'], ['internal', 'Proses', 'SOP layanan telah terdigitalisasi 80%.', 'strength'],
            ['external', 'Regulasi', 'UU Pelindungan Data Pribadi berlaku penuh; sanksi administratif hingga 2% pendapatan.', 'threat'], ['external', 'Teknologi', 'Serangan siber pada sektor publik meningkat 40% (BSSN 2025).', 'threat'],
            ['external', 'Kemitraan', 'Dukungan integrasi data dari kementerian mitra.', 'opportunity'], ['external', 'Masyarakat', 'Ekspektasi layanan digital meningkat; tingkat adopsi tinggi.', 'opportunity'],
        ];
        foreach ($factors as [$kind, $factor, $cond, $nature]) {
            ContextFactor::create(['scope_id' => $scope->id, 'kind' => $kind, 'factor' => $factor, 'condition' => $cond, 'nature' => $nature]);
        }
        $cons = [
            ['Rapat Koordinasi Penetapan Konteks & Kriteria 2026', '2026-01-20', 'Kepala Badan, Sestama, seluruh Direktur/Kepala Biro, UMR', 'Kriteria v1 ditetapkan; appetite per kategori disepakati.', 'done'],
            ['Workshop Identifikasi Risiko Unit', '2026-02-10', 'Risk Officer seluruh unit, UMR', '127 risiko teridentifikasi; 24 risiko prioritas.', 'done'],
            ['Forum Reviu Risiko Triwulan III', '2026-10-15', 'Pimpinan unit, Risk Owner, UMR, Inspektorat', null, 'planned'],
        ];
        foreach ($cons as [$t, $d, $p, $dec, $st]) {
            Consultation::create(['scope_id' => $scope->id, 'title' => $t, 'held_on' => $d, 'participants' => $p, 'decisions' => $dec, 'status' => $st]);
        }
    }

    private function seedRisks(): void
    {
        $status = ['Dalam Penanganan' => 'treating', 'Dipantau' => 'monitoring', 'Menunggu Persetujuan' => 'pending', 'Ditutup' => 'closed', 'Draft' => 'draft'];
        $treat = ['Kurangi' => 'reduce', 'Bagikan' => 'share', 'Terima' => 'retain', 'Hindari' => 'avoid'];
        $criteria = CriteriaVersion::current();
        $periods = collect(range(11, 0))->map(fn ($i) => now()->subMonths($i)->format('Y-m'));
        foreach ($this->d['RISKS'] as $r) {
            [$srcType, $srcKind] = array_map('trim', explode('·', $r['src'] . '·'));
            $owner = $this->users[$r['owner']];
            $risk = new Risk([
                'code' => str_replace('R-', 'R-2026-', $r['id']), 'name' => $r['name'], 'unit_id' => $this->unitId($r['unit']), 'objective_id' => $this->objectives[$r['obj']] ?? null,
                'process_id' => $this->processes[$r['proc']] ?? null, 'category_id' => $this->cats[$r['cat']], 'owner_id' => $owner->id,
                'cause' => ucfirst($r['cause']), 'event' => ucfirst($r['event']), 'impact' => ucfirst($r['impact']),
                'source_type' => strtolower($srcType) === 'eksternal' ? 'external' : 'internal', 'source_kind' => str_replace(' ', '_', strtolower($srcKind ?: 'process')),
                'existing_controls' => 'Kontrol yang ada: ' . implode(', ', $r['ctrl'] ?: ['belum ada kontrol formal']) . '.',
                'treatment' => $treat[$r['treat']] ?? 'reduce', 'status' => $status[$r['status']] ?? 'monitoring', 'due_date' => $r['due'] ?? null, 'criteria_version_id' => $criteria?->id,
                'inherent_l' => $r['inh'][0], 'inherent_i' => $r['inh'][1], 'residual_l' => $r['res'][0], 'residual_i' => $r['res'][1], 'target_l' => $r['tgt'][0], 'target_i' => $r['tgt'][1],
                'version' => 1, 'created_by' => $owner->id, 'updated_by' => $owner->id,
            ]);
            $resScore = $r['res'][0] * $r['res'][1];
            $risk->previous_score = $r['trend'] === 'up' ? max(1, $resScore - $this->between(1, 4)) : ($r['trend'] === 'down' ? min(25, $resScore + $this->between(1, 4)) : $resScore);
            $this->scoring->apply($risk, RiskCategory::find($this->cats[$r['cat']]));
            $risk->organization_id = $this->org->id;
            $risk->created_at = $r['created'] . ' 09:00:00';
            $risk->submitted_at = $risk->status !== 'draft' ? $r['created'] . ' 10:00:00' : null;
            $risk->approved_at = in_array($risk->status, ['treating', 'monitoring', 'closed'], true) ? \Carbon\Carbon::parse($r['created'])->addDays(5) : null;
            if ($risk->status === 'closed') {
                $risk->closed_at = now()->subDays($this->between(10, 120));
                $risk->closed_reason = 'Kontrol telah efektif; skor residual berada dalam appetite selama dua periode review.';
            }
            $risk->saveQuietly();
            $this->risks[$r['id']] = $risk;
            RiskVersion::create(['risk_id' => $risk->id, 'version' => 1, 'inherent_l' => $risk->inherent_l, 'inherent_i' => $risk->inherent_i, 'residual_l' => $risk->residual_l, 'residual_i' => $risk->residual_i, 'target_l' => $risk->target_l, 'target_i' => $risk->target_i,
                'snapshot' => $risk->only('name', 'cause', 'event', 'impact', 'treatment', 'inherent_score', 'residual_score', 'residual_level', 'evaluation'), 'note' => 'Versi awal (identifikasi)', 'created_by' => $owner->id, 'approved_at' => $risk->approved_at, 'approved_by' => $risk->approved_at ? User::where('role', 'risk_manager')->value('id') : null]);
            // Snapshot 12 bulan: bergerak dari previous_score menuju residual_score
            $start = $risk->previous_score;
            foreach ($periods as $i => $p) {
                $t = $i / 11;
                $score = (int) round($start + ($risk->residual_score - $start) * $t);
                $score = max(1, min(25, $score + ($i < 11 ? $this->between(-1, 1) : 0)));
                RiskSnapshot::create(['risk_id' => $risk->id, 'period' => $p, 'inherent_score' => $risk->inherent_score, 'residual_score' => $score, 'level' => Scoring::levelFromScore($score), 'status' => $risk->status, 'created_at' => $p . '-28 23:30:00']);
            }
        }
    }

    private function seedControls(): void
    {
        $freq = ['Harian' => 'daily', 'Bulanan' => 'monthly', 'Triwulanan' => 'quarterly', 'Semesteran' => 'semester', 'Tahunan' => 'annual', 'Berkelanjutan' => 'daily'];
        $type = ['Korektif' => 'corrective', 'Detektif' => 'detective', 'Preventif' => 'preventive'];
        $ownerUnit = ['Tim Infrastruktur' => 'ti', 'Tim Keamanan Informasi' => 'ti', 'Tim Operasi TI' => 'ti', 'Tim Pengembangan' => 'ti'];
        foreach ($this->d['CONTROLS'] as $i => $c) {
            $catRisk = collect($this->d['RISKS'])->first(fn ($r) => in_array($c['id'], $r['ctrl'], true));
            $ownerKey = $ownerUnit[$c['owner']] ?? ($catRisk['owner'] ?? 'ti');
            $f = $freq[$c['freq']] ?? 'event';
            $last = $c['last'] ?? null;
            $next = $last ? \Carbon\Carbon::parse($last)->add(match ($f) { 'daily' => '1 day', 'monthly' => '1 month', 'quarterly' => '3 months', 'semester' => '6 months', 'annual' => '1 year', default => '3 months' })->toDateString() : null;
            $control = Control::create([
                'code' => 'C-2026-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT), 'name' => $c['n'], 'objective' => $c['obj'], 'description' => "Pelaksana: {$c['owner']}. Frekuensi {$c['freq']}.",
                'owner_id' => $this->users[$ownerKey]->id, 'unit_id' => $this->users[$ownerKey]->unit_id, 'frequency' => $f, 'type' => $type[$c['type']] ?? 'preventive', 'mode' => $c['mode'] === 'Otomatis' ? 'automated' : 'manual',
                'design_eff' => $c['des'], 'operating_eff' => $c['ope'], 'last_tested_at' => $last, 'next_test_at' => $next, 'active' => true, 'created_by' => auth()->id(),
            ]);
            $this->controls[$c['id']] = $control;
            if ($last) {
                ControlAssessment::create(['control_id' => $control->id, 'tested_at' => $last, 'tester_id' => User::where('role', 'auditor')->value('id'), 'design_eff' => $c['des'], 'operating_eff' => $c['ope'], 'note' => $c['ope'] <= 2 ? 'Ditemukan pelaksanaan tidak konsisten; perlu penguatan.' : 'Kontrol berjalan sesuai desain.']);
                $prev = \Carbon\Carbon::parse($last)->subMonths(3)->toDateString();
                ControlAssessment::create(['control_id' => $control->id, 'tested_at' => $prev, 'tester_id' => User::where('role', 'auditor')->value('id'), 'design_eff' => max(1, $c['des'] - $this->between(0, 1)), 'operating_eff' => max(1, $c['ope'] - $this->between(0, 1)), 'note' => 'Pengujian periode sebelumnya.']);
            }
        }
        foreach ($this->d['RISKS'] as $r) {
            $ids = array_values(array_filter(array_map(fn ($c) => $this->controls[$c]->id ?? null, $r['ctrl'])));
            $this->risks[$r['id']]->controls()->sync($ids);
        }
    }

    private function seedActions(): void
    {
        $prio = ['Tinggi' => 'high', 'Kritis' => 'critical', 'Sedang' => 'medium', 'Rendah' => 'low'];
        $n = 0;
        foreach ($this->d['ACTIONS'] as $a) {
            $risk = $this->risks[$a['risk']] ?? null;
            if (!$risk) {
                continue;
            }
            $n++;
            $due = \Carbon\Carbon::parse($a['due']);
            $start = $due->copy()->subMonths($this->between(2, 6));
            $plan = ActionPlan::create([
                'code' => 'AP-2026-' . str_pad((string) $n, 3, '0', STR_PAD_LEFT), 'risk_id' => $risk->id, 'title' => $a['t'], 'description' => "Pelaksana: {$a['pic']}.", 'pic_id' => $risk->owner_id, 'unit_id' => $risk->unit_id,
                'budget' => ($a['budget'] ?? 0) * 1e6, 'priority' => $prio[$a['prio']] ?? 'medium', 'start_date' => $start, 'due_date' => $due, 'progress' => $a['prog'],
                'expected_dl' => $a['prog'] < 100 && $risk->residual_l > $risk->target_l ? 1 : 0, 'expected_di' => $a['prog'] < 100 && $risk->residual_i > $risk->target_i && $this->rnd() > 0.6 ? 1 : 0,
                'completed_at' => $a['prog'] >= 100 ? $due->copy()->subDays($this->between(0, 20)) : null, 'created_by' => $risk->owner_id,
            ]);
            if ($a['prog'] > 0) {
                $steps = $a['prog'] >= 100 ? [40, 100] : [$a['prog']];
                $from = 0;
                foreach ($steps as $to) {
                    $this->mk(ActionProgress::class, ['action_plan_id' => $plan->id, 'user_id' => $risk->owner_id, 'from_pct' => $from, 'to_pct' => $to, 'note' => $to >= 100 ? 'Seluruh kegiatan selesai; bukti terlampir.' : 'Progres sesuai rencana.', 'created_at' => $start->copy()->addDays($this->between(5, 40))]);
                    $from = $to;
                }
            }
        }
    }

    private function seedKris(): void
    {
        $periods = collect(range(11, 0))->map(fn ($i) => now()->subMonths($i)->format('Y-m-01'));
        $alerts = app(\App\Services\AlertService::class);
        foreach ($this->d['KRIS'] as $i => $k) {
            $risk = $this->risks[$k['risk']] ?? null;
            $upBad = $k['g'] <= $k['r'];
            $kri = Kri::create([
                'code' => 'KRI-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT), 'name' => $k['n'], 'unit' => $k['u'], 'risk_id' => $risk?->id, 'owner_id' => $risk?->owner_id, 'source' => 'manual', 'frequency' => 'monthly',
                'direction' => $upBad ? 'up_bad' : 'down_bad', 'threshold_warn' => $k['g'], 'threshold_crit' => $k['r'], 'decimals' => $k['fmt'] ?? 0, 'active' => true, 'status' => 'normal',
            ]);
            foreach ($k['v'] as $j => $v) {
                $this->mk(KriValue::class, ['kri_id' => $kri->id, 'period' => $periods[$j], 'value' => $v, 'entered_by' => $risk?->owner_id, 'created_at' => $periods[$j]]);
            }
            $last = end($k['v']);
            $prev = $kri->status;
            $kri->update(['last_value' => $last, 'status' => $kri->statusFor((float) $last)]);
            $alerts->checkKri($kri->fresh(), $prev);
        }
    }

    private function seedIncidents(): void
    {
        $status = ['Ditutup' => 'closed', 'Investigasi' => 'investigating', 'Tindakan Korektif' => 'corrective', 'Dilaporkan' => 'reported'];
        foreach ($this->d['INCIDENTS'] as $inc) {
            $risk = $this->risks[$inc['risk']] ?? null;
            $incident = Incident::create([
                'code' => $inc['id'], 'occurred_at' => $inc['date'] . ' ' . sprintf('%02d:%02d:00', $this->between(1, 20), $this->between(0, 59)), 'location' => $inc['loc'], 'risk_id' => $risk?->id, 'unit_id' => $risk?->unit_id, 'title' => $inc['t'],
                'chronology' => $inc['chrono'], 'cause' => $inc['cause'], 'impact' => $inc['impact'], 'loss_amount' => ($inc['loss'] ?? 0) * 1e6, 'loss_type' => ($inc['loss'] ?? 0) > 0 ? 'financial' : 'operational',
                'response' => $inc['response'], 'corrective_action' => $inc['corrective'], 'status' => $status[$inc['status']] ?? 'reported', 'reported_by' => $risk?->owner_id, 'closed_at' => ($inc['status'] ?? '') === 'Ditutup' ? \Carbon\Carbon::parse($inc['date'])->addDays($this->between(3, 30)) : null,
            ]);
            if ($incident->loss_amount > 0) {
                LossEvent::create(['incident_id' => $incident->id, 'category_id' => $risk?->category_id, 'year' => $incident->occurred_at->year, 'risk_name' => $risk?->name ?? $incident->title, 'event' => $incident->title, 'amount' => $incident->loss_amount, 'description' => $incident->impact]);
            }
            if ($incident->status === 'closed') {
                Lesson::create(['subject_type' => 'incident', 'subject_id' => $incident->id, 'text' => 'Pembelajaran: ' . $inc['corrective'], 'created_by' => $risk?->owner_id]);
            }
        }
        foreach ($this->d['LOSS_HISTORY'] as $l) {
            LossEvent::create(['category_id' => $this->cats['Operasional'] ?? null, 'year' => $l['y'], 'risk_name' => $l['risk'], 'event' => $l['ev'], 'amount' => $l['loss'] * 1e6, 'description' => 'Data historis.']);
        }
    }

    private function seedReviews(): void
    {
        $manager = User::where('role', 'risk_manager')->first();
        foreach ($this->d['REVIEWS'] as $rv) {
            $risk = $this->risks[$rv['r']] ?? null;
            if (!$risk) {
                continue;
            }
            $this->mk(Review::class, ['period_type' => 'quarterly', 'period' => '2026-Q3', 'risk_id' => $risk->id, 'previous_score' => $rv['prev'], 'current_score' => $rv['cur'],
                'trend' => $rv['cur'] > $rv['prev'] ? 'up' : ($rv['cur'] < $rv['prev'] ? 'down' : 'flat'), 'note' => $rv['note'], 'decision' => $rv['cur'] >= 16 ? 'escalate' : ($rv['cur'] > $rv['prev'] ? 'change_treatment' : 'continue'),
                'reviewer_id' => $manager?->id, 'signed_at' => now()->subDays($this->between(1, 25)), 'created_at' => now()->subDays($this->between(1, 25))]);
        }
    }

    private function seedDocuments(): void
    {
        $type = ['Kebijakan' => 'policy', 'SOP' => 'sop', 'Hasil Pengujian' => 'test', 'Berita Acara' => 'minutes', 'Laporan' => 'report', 'Kontrak' => 'contract', 'Sertifikat' => 'certificate', 'Foto' => 'photo', 'Hasil Audit' => 'audit', 'Screenshot' => 'screenshot'];
        $st = ['Disetujui' => 'approved', 'Review' => 'review', 'Draft' => 'draft'];
        $dir = 'documents/' . $this->org->id . '/demo';
        foreach ($this->d['DOCS'] as $i => $doc) {
            $subject = null;
            if (preg_match('/^R-\d+$/', $doc['ref'] ?? '') && isset($this->risks[$doc['ref']])) {
                $subject = $this->risks[$doc['ref']];
            } elseif (preg_match('/^INC-/', $doc['ref'] ?? '')) {
                $subject = Incident::where('code', $doc['ref'])->first();
            } elseif (preg_match('/^C-\d+$/', $doc['ref'] ?? '') && isset($this->controls[$doc['ref']])) {
                $subject = $this->controls[$doc['ref']];
            }
            $name = 'demo-' . ($i + 1) . '.pdf';
            $pdf = $this->pdf($doc['n'], "Dokumen demo ManRisk ERM — {$doc['type']} — {$doc['v']} — " . ($doc['by'] ?? ''));
            Storage::disk('local')->put("$dir/$name", $pdf);
            $this->mk(Document::class, ['subject_type' => $subject?->getMorphClass(), 'subject_id' => $subject?->getKey(), 'type' => $type[$doc['type']] ?? 'evidence', 'title' => $doc['n'], 'version' => (int) preg_replace('/\D/', '', explode('.', $doc['v'])[0]) ?: 1,
                'path' => "$dir/$name", 'original_name' => preg_replace('/[^\w .()\-]/u', '_', $doc['n']) . '.pdf', 'mime' => 'application/pdf', 'size' => strlen($pdf), 'hash' => hash('sha256', $pdf), 'uploaded_by' => auth()->id(),
                'expires_at' => ($doc['exp'] ?? '—') !== '—' ? $doc['exp'] : null, 'status' => $st[$doc['st']] ?? 'draft', 'created_at' => $doc['d'] . ' 10:00:00']);
        }
    }

    private function pdf(string $title, string $body): string
    {
        $esc = fn ($s) => str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], iconv('UTF-8', 'ASCII//TRANSLIT', $s) ?: $s);
        $stream = "BT /F1 16 Tf 50 780 Td ({$esc($title)}) Tj 0 -30 Td /F1 11 Tf ({$esc($body)}) Tj ET";
        $objs = ["<< /Type /Catalog /Pages 2 0 R >>", "<< /Type /Pages /Kids [3 0 R] /Count 1 >>", "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>",
            "<< /Length " . strlen($stream) . " >>\nstream\n$stream\nendstream", "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>"];
        $out = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objs as $i => $o) {
            $offsets[] = strlen($out);
            $out .= ($i + 1) . " 0 obj\n$o\nendobj\n";
        }
        $xref = strlen($out);
        $out .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $out .= sprintf("%010d 00000 n \n", $o);
        }
        return $out . "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
    }

    private function seedImprovements(): void
    {
        $src = ['Kegagalan Kontrol' => 'control_failure', 'Lessons Learned' => 'lesson', 'Insiden' => 'incident', 'Temuan Audit' => 'audit', 'Pelanggaran KRI' => 'kri_breach', 'Evaluasi Treatment' => 'treatment', 'Tren Risiko' => 'trend', 'Hasil Review' => 'review'];
        $st = ['Belum Mulai' => 'open', 'Berjalan' => 'in_progress', 'Selesai' => 'done'];
        foreach ($this->d['IMPROVE'] as $i => $im) {
            Improvement::create(['code' => 'IMP-2026-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT), 'source_type' => $src[$im['src']] ?? 'other', 'source_ref' => $im['ref'], 'title' => $im['t'], 'description' => "Penanggung jawab: {$im['pic']}.",
                'pic_id' => User::where('role', 'risk_manager')->value('id'), 'unit_id' => $this->units[$im['pic']] ?? null, 'due_date' => $im['due'], 'status' => $st[$im['st']] ?? 'open']);
        }
        $lessons = ['Pengujian kontrol harus dijadwalkan di kalender bersama agar tidak terlewat.', 'KRI yang dipantau mingguan memberi peringatan lebih dini dibanding bulanan.', 'Integrasi data kepegawaian dengan direktori akun mencegah akun yatim.'];
        foreach ($lessons as $l) {
            Lesson::create(['text' => $l, 'created_by' => User::where('role', 'risk_manager')->value('id')]);
        }
    }

    private function seedApprovals(): void
    {
        $svc = app(\App\Services\ApprovalService::class);
        $manager = User::where('role', 'risk_manager')->first();
        $n = 0;
        foreach ($this->risks as $risk) {
            if ($risk->status !== 'pending') {
                continue;
            }
            $n++;
            $requester = $risk->owner;
            $approval = $this->mk(Approval::class, ['code' => 'WF-2026-' . str_pad((string) $n, 3, '0', STR_PAD_LEFT), 'type' => $n % 3 === 0 ? 'score_change' : 'new_risk', 'subject_type' => 'risk', 'subject_id' => $risk->id, 'requester_id' => $requester->id,
                'current_step' => 1, 'total_steps' => 0, 'status' => 'pending', 'note' => $n % 3 === 0 ? 'Skor residual naik setelah insiden terbaru.' : 'Pengajuan risiko baru hasil identifikasi unit.', 'created_at' => now()->subDays($this->between(0, 6))]);
            $chain = $svc->chainFor($risk, $approval->type, $requester);
            $approval->update(['total_steps' => count($chain)]);
            foreach ($chain as $i => $role) {
                ApprovalStep::create(['approval_id' => $approval->id, 'step_no' => $i + 1, 'role' => $role, 'due_at' => $i === 0 ? $approval->created_at->addWeekdays(3) : null]);
            }
        }
        // Riwayat yang sudah disetujui
        foreach (array_slice(array_values($this->risks), 0, 12) as $i => $risk) {
            if ($risk->status === 'pending') {
                continue;
            }
            $n++;
            $approval = $this->mk(Approval::class, ['code' => 'WF-2026-' . str_pad((string) $n, 3, '0', STR_PAD_LEFT), 'type' => 'new_risk', 'subject_type' => 'risk', 'subject_id' => $risk->id, 'requester_id' => $risk->owner_id, 'current_step' => 2, 'total_steps' => 2,
                'status' => $i === 5 ? 'rejected' : 'approved', 'note' => 'Pengajuan risiko baru.', 'decided_at' => $risk->approved_at ?? now()->subDays(30), 'created_at' => ($risk->approved_at ?? now()->subDays(30))->copy()->subDays(4)]);
            ApprovalStep::create(['approval_id' => $approval->id, 'step_no' => 1, 'role' => 'risk_manager', 'approver_id' => $manager?->id, 'action' => 'approve', 'note' => 'Sesuai kriteria.', 'acted_at' => $approval->created_at->addDays(2)]);
            ApprovalStep::create(['approval_id' => $approval->id, 'step_no' => 2, 'role' => 'management', 'approver_id' => User::where('role', 'management')->value('id'), 'action' => $i === 5 ? 'reject' : 'approve', 'note' => $i === 5 ? 'Perlu data dukung tambahan.' : 'Disetujui.', 'acted_at' => $approval->decided_at]);
        }
    }

    private function seedFrameworkScores(): void
    {
        $scores = ['4.a' => ['met', 85], '4.b' => ['met', 90], '4.c' => ['met', 80], '4.d' => ['partial', 65], '4.e' => ['met', 82], '4.f' => ['partial', 70], '4.g' => ['partial', 55], '4.h' => ['partial', 60],
            '5.2' => ['met', 88], '5.3' => ['partial', 72], '5.4' => ['met', 85], '5.5' => ['met', 80], '5.6' => ['partial', 68], '5.7' => ['partial', 60],
            '6.2' => ['met', 78], '6.3' => ['met', 90], '6.4.2' => ['met', 92], '6.4.3' => ['met', 88], '6.4.4' => ['met', 85], '6.5' => ['partial', 70], '6.6' => ['met', 80], '6.7' => ['met', 86]];
        foreach ($scores as $clause => [$status, $score]) {
            \App\Models\FrameworkItem::where('clause', $clause)->update(['status' => $status, 'score' => $score, 'note' => $status === 'partial' ? 'Perlu penguatan pada periode berikutnya.' : 'Terpenuhi, dipantau berkala.']);
        }
    }
}

<?php

namespace App\Services\Ai;

use App\Models\OrgUnit;
use App\Services\AiService;

/**
 * Mengubah permintaan bebas ("tampilkan jumlah risiko per unit dan tren insiden 12 bulan")
 * menjadi spesifikasi dashboard. Dengan kunci API, model AI hanya menerima skema dataset
 * (tanpa data) lalu menyusun JSON widget; tanpa kunci API dipakai pengurai kata kunci lokal.
 * Keluaran selalu divalidasi AnalyticsEngine sebelum dijalankan.
 */
class AnalyticsPlanner
{
    private const DATASET_WORDS = [
        'snapshots' => ['snapshot', 'riwayat skor', 'tren skor', 'skor bulanan', 'perkembangan skor', 'histori skor'],
        'action_plans' => ['action plan', 'rencana aksi', 'mitigasi', 'rtl', 'tindak lanjut', 'rencana tindak'],
        'kris' => ['kri', 'indikator risiko', 'key risk', 'early warning'],
        'losses' => ['loss event', 'database kerugian', 'basis data kerugian'],
        'incidents' => ['insiden', 'kejadian', 'kerugian'],
        'controls' => ['kontrol', 'pengendalian'],
        'improvements' => ['perbaikan', 'improvement'],
        'reviews' => ['review', 'tinjauan'],
        'approvals' => ['persetujuan', 'approval', 'pengajuan'],
        'risks' => ['risiko', 'register', 'risk'],
    ];

    private const GROUP_WORDS = [
        'inherent_level' => ['level inheren', 'level risiko inheren'],
        'unit' => ['per unit', 'unit kerja', 'setiap unit', 'tiap unit', 'antar unit', 'per divisi', 'per direktorat', 'per bagian', 'per biro', 'per satuan'],
        'theme' => ['per tema', 'tema risiko', ' t1'],
        'category' => ['kategori', 'taksonomi'],
        'level' => ['level', 'tingkat risiko'],
        'evaluation' => ['evaluasi'],
        'treatment' => ['treatment', 'perlakuan', 'opsi penanganan'],
        'source_kind' => ['sumber risiko', 'sumber'],
        'source' => ['sumber perbaikan', 'sumber'],
        'owner' => ['pemilik', 'owner'],
        'priority' => ['prioritas'],
        'pic' => ['per pic', 'penanggung jawab'],
        'effectiveness' => ['efektivitas', 'keefektifan'],
        'type' => ['tipe', 'jenis kontrol', 'jenis pengajuan', 'jenis persetujuan'],
        'loss_type' => ['jenis kerugian'],
        'decision' => ['keputusan'],
        'test_due' => ['jadwal uji', 'status pengujian'],
        'risk' => ['per risiko', 'setiap risiko'],
        'year' => ['per tahun', 'tahunan'],
        'status' => ['status'],
    ];

    private const DEFAULT_MEASURE = ['risks' => 'residual_score', 'action_plans' => 'progress', 'incidents' => 'loss_amount', 'controls' => 'operating_eff', 'approvals' => 'days_open', 'snapshots' => 'residual_score', 'reviews' => 'current_score', 'losses' => 'amount', 'kris' => 'last_value'];

    public function __construct(private AnalyticsEngine $engine, private AiService $ai)
    {
    }

    /** @return array{spec: array, model: string, remaining: int, local: bool} */
    public function plan(string $prompt): array
    {
        $user = $this->engine->datasets()->user();
        $prompt = trim(mb_substr(strip_tags($prompt), 0, 1000));
        $heuristic = fn () => json_encode($this->heuristic($prompt), JSON_UNESCAPED_UNICODE);
        $res = $this->ai->complete($user, 'analytics', $this->system(), [['role' => 'user', 'content' => $prompt]], $heuristic, 2000);
        $spec = $this->parse($res['text']);
        if (!$spec['widgets']) {
            $spec = $this->parse($heuristic());
        }
        return ['spec' => $spec, 'model' => $res['model'], 'remaining' => $res['remaining'], 'local' => $res['local']];
    }

    /** Ambil JSON dari jawaban dan buang widget yang tidak valid. */
    public function parse(string $text): array
    {
        $json = null;
        if (preg_match('/\{.*\}/s', $text, $m)) {
            $json = json_decode($m[0], true);
        }
        $widgets = [];
        foreach (array_slice((array) ($json['widgets'] ?? []), 0, AnalyticsEngine::MAX_WIDGETS) as $w) {
            if (is_array($w) && ($n = $this->engine->normalize($w))) {
                $widgets[] = $n;
            }
        }
        return ['title' => trim(mb_substr(strip_tags((string) ($json['title'] ?? 'Dashboard analitik')), 0, 120)) ?: 'Dashboard analitik', 'widgets' => $widgets];
    }

    private function system(): string
    {
        $schema = json_encode($this->engine->datasets()->schema(), JSON_UNESCAPED_UNICODE);
        return <<<TXT
Anda adalah perancang dashboard untuk aplikasi manajemen risiko ManRisk ERM (ISO 31000, PER-2/MBU/03/2023).
Ubah permintaan pengguna menjadi spesifikasi dashboard. Anda TIDAK melihat data; aplikasi yang menghitung.
Balas HANYA JSON tanpa penjelasan:
{"title":"judul dashboard","widgets":[{"type":"kpi|bar|hbar|line|pie|table","title":"judul singkat","dataset":"<kunci dataset>","metric":"count|sum:<measure>|avg:<measure>|max:<measure>|min:<measure>","group":"<dimension>|null","series":"<dimension kedua>|null","time":{"field":"<date>","bucket":"month|quarter|year"}|null,"filters":{"<dimension>":["nilai"]},"range":{"last_months":12}|{"year":2026}|{},"list":false,"sort":"desc|asc","limit":10}]}
Aturan: maksimal 8 widget; kpi tanpa group/time; line untuk tren waktu (isi time); pie untuk komposisi (≤ 8 kelompok);
hbar untuk peringkat unit/kategori; table dengan list=true untuk daftar data teratas (urut menurut metric).
Nilai filter boleh label Indonesia (mis. "Terlambat", "Kritis", "Tinggi") atau kode (overdue, critical, high).
Risiko yang ditutup tidak dihitung kecuali difilter status. Bila permintaan umum ("dashboard risiko"), susun 5–7 widget yang paling berguna.
Gunakan hanya kunci berikut:
$schema
TXT;
    }

    // ---------- pengurai lokal ----------

    public function heuristic(string $prompt): array
    {
        $p = ' ' . mb_strtolower(preg_replace('/\s+/', ' ', $prompt)) . ' ';
        $clauses = $this->clauses($p);
        $widgets = [];
        $prevDs = null;
        foreach ($clauses as $c) {
            $ds = $this->dataset($c) ?? $prevDs ?? 'risks';
            $prevDs = $ds;
            $w = $this->widget($c, $ds);
            if ($w === null) {
                array_push($widgets, ...$this->template($ds, $c));
            } else {
                $widgets[] = $w;
            }
        }
        $widgets = array_slice($widgets, 0, 8);
        $title = trim($prompt) !== '' ? mb_strtoupper(mb_substr(trim($prompt), 0, 1)) . mb_substr(trim($prompt), 1, 79) : 'Dashboard risiko';
        return ['title' => $title, 'widgets' => $widgets];
    }

    private function clauses(string $p): array
    {
        $starter = '(?:jumlah|total|tren|grafik|daftar|tabel|top|rata|distribusi|komposisi|sebaran|proporsi|perbandingan|nilai|berapa|tampilkan|' . implode('|', array_map(fn ($w) => preg_quote($w, '/'), array_merge(...array_values(self::DATASET_WORDS)))) . ')';
        $parts = preg_split('/\s*(?:;|\n|\s+serta\s+|\s+lalu\s+|\s+kemudian\s+|,\s*(?=' . $starter . ')|\s+dan\s+(?=' . $starter . '))\s*/u', $p) ?: [$p];
        return array_values(array_filter(array_map('trim', $parts), fn ($x) => mb_strlen($x) > 1)) ?: ['risiko'];
    }

    private function has(string $c, array $words): bool
    {
        foreach ($words as $w) {
            if (preg_match('/(?<![\p{L}\d])' . preg_quote(trim($w), '/') . '(?:nya)?(?![\p{L}\d])/u', $c)) {
                return true;
            }
        }
        return false;
    }

    private function dataset(string $c): ?string
    {
        foreach (self::DATASET_WORDS as $ds => $words) {
            if ($this->has($c, $words)) {
                return $ds;
            }
        }
        return null;
    }

    /** null = permintaan umum → pakai templat dataset. */
    private function widget(string $c, string $ds): ?array
    {
        $defs = $this->engine->datasets()->defs()[$ds];
        $dims = [];
        foreach (self::GROUP_WORDS as $dim => $words) {
            if (isset($defs['dims'][$dim]) && $this->has($c, $words) && !in_array($dim, $dims, true)) {
                if ($dim === 'level' && in_array('inherent_level', $dims, true)) {
                    continue;
                }
                $dims[] = $dim;
            }
        }
        // kata "status" juga dipakai sebagai filter ("status terlambat") — tetap dimensi bila tidak ada nilai filter
        $bucket = $this->has($c, ['triwulan', 'kuartal', 'quarter']) ? 'quarter' : ($this->has($c, ['per bulan', 'bulanan', 'tiap bulan', 'setiap bulan', 'tren', 'perkembangan', 'dari waktu ke waktu', 'bulan terakhir']) ? 'month' : null);
        if (in_array('year', $dims, true) && $ds !== 'losses') {
            $bucket = 'year';
            $dims = array_values(array_diff($dims, ['year']));
        }
        if ($ds === 'snapshots') {
            $bucket = $bucket ?? 'month';
            $dims = array_values(array_diff($dims, ['period']));
        }
        $metric = $this->metric($c, $ds);
        $list = $this->has($c, ['daftar', 'list', 'top', 'teratas', 'tertinggi', 'terbesar', 'terbanyak', 'terendah', 'terkecil', 'paling']) && !$dims && !$bucket;
        $filters = $this->filters($c, $ds, []);
        // dimensi yang sudah menjadi filter ("risiko level tinggi") tidak sekaligus dijadikan kelompok, kecuali eksplisit "per level"
        foreach (array_keys($filters) as $fd) {
            if (in_array($fd, $dims, true)) {
                if ($this->has($c, ['per ' . $fd, 'per level', 'per status', 'berdasarkan level', 'berdasarkan status', 'per evaluasi'])) {
                    unset($filters[$fd]);
                } else {
                    $dims = array_values(array_diff($dims, [$fd]));
                }
            }
        }
        $range = $this->range($c);
        $general = !$dims && !$bucket && !$list && !$filters && $metric === 'count' && !$range && $this->has($c, ['dashboard', 'ringkasan', 'overview', 'eksekutif', 'profil', 'gambaran', 'monitoring', 'analisis', 'analitik']);
        if ($general || (!$dims && !$bucket && !$list && !$filters && $metric === 'count' && !$range && !$this->has($c, ['jumlah', 'total', 'berapa', 'banyaknya']))) {
            return null;
        }
        $type = $list || $this->has($c, ['tabel']) ? 'table' : ($this->has($c, ['pie', 'donat', 'donut', 'proporsi', 'komposisi', 'persentase', 'porsi']) ? 'pie'
            : ($bucket ? ($this->has($c, ['batang', 'bar']) ? 'bar' : 'line') : ($dims ? (in_array($dims[0], ['unit', 'category', 'owner', 'pic', 'risk', 'theme'], true) || $this->has($c, ['horizontal', 'peringkat', 'ranking']) ? 'hbar' : 'bar') : 'kpi')));
        if ($bucket && !$range) {
            $range = $bucket === 'month' ? ['last_months' => 12] : [];
        }
        $limit = preg_match('/(?<!\d)(\d{1,2})(?!\d)(?!\s*(?:bulan|tahun|hari|minggu|%))/u', $c, $m) ? (int) $m[1] : ($list ? 10 : 12);
        $title = trim(preg_replace('/^(tampilkan|buatkan|buat|lihat|berapa|grafik|tolong)\s+/u', '', trim($c)));
        $title = preg_replace_callback('/\b(kri|rmi|kks|pic|t1|t2|t3|bumn|sla)\b/u', fn ($m) => mb_strtoupper($m[1]), $title);
        return ['title' => mb_strlen($title) <= 80 ? mb_strtoupper(mb_substr($title, 0, 1)) . mb_substr($title, 1) : null, 'type' => $type, 'dataset' => $ds, 'metric' => $metric, 'group' => $dims[0] ?? null, 'series' => $dims[1] ?? null,
            'time' => $bucket ? ['field' => $defs['default_date'], 'bucket' => $bucket] : null, 'filters' => $filters, 'range' => $range, 'list' => $list,
            'sort' => $this->has($c, ['terendah', 'terkecil', 'paling rendah', 'paling kecil']) ? 'asc' : 'desc', 'limit' => max(1, min(50, $limit))];
    }

    private function metric(string $c, string $ds): string
    {
        $m = [
            'risks' => [['eksposur inheren', 'inherent_exposure'], ['eksposur', 'residual_exposure'], ['skor inheren', 'inherent_score'], ['skor target', 'target_score'], ['skor', 'residual_score']],
            'action_plans' => [['realisasi biaya', 'actual_cost'], ['realisasi', 'actual_cost'], ['anggaran', 'budget'], ['biaya', 'budget'], ['progres', 'progress'], ['kemajuan', 'progress']],
            'incidents' => [['kerugian', 'loss_amount'], ['nilai', 'loss_amount']],
            'losses' => [['kerugian', 'amount'], ['nilai', 'amount']],
            'controls' => [['efektivitas desain', 'design_eff'], ['efektivitas operasi', 'operating_eff']],
            'approvals' => [['lama', 'days_open'], ['sla', 'days_open'], ['durasi', 'days_open']],
            'snapshots' => [['inheren', 'inherent_score'], ['skor', 'residual_score']],
            'reviews' => [['skor', 'current_score']],
            'kris' => [['nilai', 'last_value']],
        ][$ds] ?? [];
        $field = null;
        foreach ($m as [$w, $f]) {
            if ($this->has($c, [$w])) {
                $field = $f;
                break;
            }
        }
        $avg = $this->has($c, ['rata-rata', 'rata rata', 'rerata', 'average', 'rata2']);
        if ($ds === 'snapshots') {
            return 'avg:' . ($field ?? 'residual_score');
        }
        if ($avg) {
            return 'avg:' . ($field ?? self::DEFAULT_MEASURE[$ds]);
        }
        if ($field && $this->has($c, ['maksimum', 'maksimal', 'tertinggi']) && !$this->has($c, ['daftar', 'top', 'teratas'])) {
            return "max:$field";
        }
        if ($field && in_array($field, ['residual_score', 'inherent_score', 'target_score', 'progress', 'design_eff', 'operating_eff', 'days_open', 'current_score', 'last_value'], true)) {
            return $this->has($c, ['daftar', 'top', 'teratas', 'tertinggi', 'terendah']) ? "max:$field" : "avg:$field";
        }
        return $field ? "sum:$field" : 'count';
    }

    private function filters(string $c, string $ds, array $dims): array
    {
        $f = [];
        $add = function (string $dim, array $vals) use (&$f, $ds, $dims) {
            if (isset($this->engine->datasets()->defs()[$ds]['dims'][$dim]) && !in_array($dim, $dims, true)) {
                $f[$dim] = $vals;
            }
        };
        switch ($ds) {
            case 'risks':
                if ($this->has($c, ['sangat tinggi', 'very high'])) {
                    $add('level', ['very_high']);
                } elseif ($this->has($c, ['risiko tinggi', 'level tinggi', 'berlevel tinggi', 'high'])) {
                    $add('level', ['high', 'very_high']);
                }
                if ($this->has($c, ['kritis'])) {
                    $add('evaluation', ['critical']);
                } elseif ($this->has($c, ['eskalasi'])) {
                    $add('evaluation', ['escalate', 'critical']);
                } elseif ($this->has($c, ['melewati toleransi', 'di atas appetite', 'perlu penanganan'])) {
                    $add('evaluation', ['treat', 'escalate', 'critical']);
                }
                if ($this->has($c, ['ditutup', 'tertutup'])) {
                    $add('status', ['closed']);
                } elseif ($this->has($c, ['draft'])) {
                    $add('status', ['draft']);
                } elseif ($this->has($c, ['menunggu persetujuan'])) {
                    $add('status', ['pending']);
                }
                if ($this->has($c, ['naik', 'memburuk', 'meningkat'])) {
                    $add('trend', ['up']);
                }
                break;
            case 'action_plans':
                if ($this->has($c, ['terlambat', 'overdue', 'lewat tenggat', 'melewati tenggat'])) {
                    $add('status', ['overdue']);
                } elseif ($this->has($c, ['menunggu verifikasi'])) {
                    $add('status', ['verify']);
                } elseif ($this->has($c, ['selesai'])) {
                    $add('status', ['done']);
                } elseif ($this->has($c, ['berjalan', 'belum selesai'])) {
                    $add('status', ['running', 'todo', 'overdue']);
                }
                if ($this->has($c, ['prioritas tinggi', 'prioritas kritis'])) {
                    $add('priority', ['high', 'critical']);
                }
                break;
            case 'kris':
                if ($this->has($c, ['kritis'])) {
                    $add('status', ['critical']);
                } elseif ($this->has($c, ['waspada', 'warning'])) {
                    $add('status', ['warning']);
                } elseif ($this->has($c, ['melewati ambang', 'breach', 'melampaui'])) {
                    $add('status', ['warning', 'critical']);
                }
                break;
            case 'incidents':
                if ($this->has($c, ['terbuka', 'belum ditutup', 'belum selesai', 'aktif'])) {
                    $add('status', ['reported', 'investigating', 'corrective']);
                } elseif ($this->has($c, ['ditutup', 'selesai'])) {
                    $add('status', ['closed']);
                }
                break;
            case 'controls':
                if ($this->has($c, ['lemah', 'tidak efektif', 'kurang efektif'])) {
                    $add('effectiveness', ['Tidak Efektif', 'Sebagian Efektif']);
                }
                if ($this->has($c, ['jatuh tempo', 'belum diuji', 'terlambat uji'])) {
                    $add('test_due', ['Jatuh tempo']);
                }
                break;
            case 'improvements':
                if ($this->has($c, ['terlambat'])) {
                    $add('overdue', ['Terlambat']);
                }
                if ($this->has($c, ['terbuka', 'belum selesai'])) {
                    $add('status', ['open', 'in_progress']);
                }
                break;
            case 'approvals':
                if ($this->has($c, ['menunggu', 'pending', 'tertunda'])) {
                    $add('status', ['pending']);
                }
                break;
        }
        // nama unit yang disebut → filter unit (bila tidak mengelompokkan per unit)
        if (!in_array('unit', $dims, true) && isset($this->engine->datasets()->defs()[$ds]['dims']['unit'])) {
            $ids = $this->engine->datasets()->user()->accessibleUnitIds();
            foreach (OrgUnit::when($ids !== null, fn ($q) => $q->whereIn('id', $ids))->get(['id', 'name', 'code']) as $u) {
                if (mb_strlen($u->name) >= 4 && str_contains($c, mb_strtolower($u->name))) {
                    $f['unit'][] = (string) $u->id;
                }
            }
        }
        return $f;
    }

    private function range(string $c): array
    {
        if (preg_match('/(\d{1,2})\s*bulan terakhir/u', $c, $m)) {
            return ['last_months' => (int) $m[1]];
        }
        if ($this->has($c, ['setahun terakhir', 'satu tahun terakhir', '1 tahun terakhir'])) {
            return ['last_months' => 12];
        }
        if ($this->has($c, ['tahun ini', 'tahun berjalan'])) {
            return ['year' => (int) now()->year];
        }
        if ($this->has($c, ['tahun lalu'])) {
            return ['year' => (int) now()->year - 1];
        }
        if (preg_match('/(?<!\d)(20\d{2})(?!\d)/', $c, $m)) {
            return ['year' => (int) $m[1]];
        }
        return [];
    }

    private function template(string $ds, string $c): array
    {
        $all = $this->has($c, ['eksekutif', 'lengkap', 'menyeluruh', 'organisasi']) && $ds === 'risks';
        $t = match ($ds) {
            'risks' => [
                ['type' => 'kpi', 'title' => 'Risiko aktif', 'dataset' => 'risks', 'metric' => 'count'],
                ['type' => 'kpi', 'title' => 'Risiko level tinggi', 'dataset' => 'risks', 'metric' => 'count', 'filters' => ['level' => ['high', 'very_high']]],
                ['type' => 'kpi', 'title' => 'Total eksposur residual', 'dataset' => 'risks', 'metric' => 'sum:residual_exposure'],
                ['type' => 'kpi', 'title' => 'Rata-rata skor residual', 'dataset' => 'risks', 'metric' => 'avg:residual_score'],
                ['type' => 'pie', 'title' => 'Komposisi level risiko residual', 'dataset' => 'risks', 'metric' => 'count', 'group' => 'level'],
                ['type' => 'hbar', 'title' => 'Risiko per unit kerja dan level', 'dataset' => 'risks', 'metric' => 'count', 'group' => 'unit', 'series' => 'level', 'limit' => 10],
                ['type' => 'bar', 'title' => 'Risiko per kategori', 'dataset' => 'risks', 'metric' => 'count', 'group' => 'category', 'limit' => 10],
                ['type' => 'line', 'title' => 'Tren rata-rata skor residual 12 bulan', 'dataset' => 'snapshots', 'metric' => 'avg:residual_score', 'time' => ['field' => 'period', 'bucket' => 'month'], 'range' => ['last_months' => 12]],
                ['type' => 'table', 'title' => '10 risiko dengan skor residual tertinggi', 'dataset' => 'risks', 'metric' => 'max:residual_score', 'list' => true, 'limit' => 10],
            ],
            'action_plans' => [
                ['type' => 'kpi', 'title' => 'Action plan', 'dataset' => 'action_plans', 'metric' => 'count', 'filters' => ['status' => ['todo', 'running', 'overdue', 'verify', 'done']]],
                ['type' => 'kpi', 'title' => 'Terlambat', 'dataset' => 'action_plans', 'metric' => 'count', 'filters' => ['status' => ['overdue']]],
                ['type' => 'kpi', 'title' => 'Rata-rata progres', 'dataset' => 'action_plans', 'metric' => 'avg:progress', 'filters' => ['status' => ['todo', 'running', 'overdue', 'verify', 'done']]],
                ['type' => 'kpi', 'title' => 'Total anggaran', 'dataset' => 'action_plans', 'metric' => 'sum:budget'],
                ['type' => 'pie', 'title' => 'Status action plan', 'dataset' => 'action_plans', 'metric' => 'count', 'group' => 'status'],
                ['type' => 'hbar', 'title' => 'Action plan per unit dan status', 'dataset' => 'action_plans', 'metric' => 'count', 'group' => 'unit', 'series' => 'status', 'limit' => 10],
                ['type' => 'table', 'title' => 'Action plan terlambat', 'dataset' => 'action_plans', 'metric' => 'count', 'list' => true, 'sort' => 'asc', 'filters' => ['status' => ['overdue']], 'limit' => 10],
            ],
            'kris' => [
                ['type' => 'kpi', 'title' => 'KRI aktif', 'dataset' => 'kris', 'metric' => 'count'],
                ['type' => 'kpi', 'title' => 'KRI kritis', 'dataset' => 'kris', 'metric' => 'count', 'filters' => ['status' => ['critical']]],
                ['type' => 'kpi', 'title' => 'KRI waspada', 'dataset' => 'kris', 'metric' => 'count', 'filters' => ['status' => ['warning']]],
                ['type' => 'pie', 'title' => 'Status KRI', 'dataset' => 'kris', 'metric' => 'count', 'group' => 'status'],
                ['type' => 'hbar', 'title' => 'KRI per unit dan status', 'dataset' => 'kris', 'metric' => 'count', 'group' => 'unit', 'series' => 'status'],
                ['type' => 'table', 'title' => 'KRI yang melewati ambang', 'dataset' => 'kris', 'metric' => 'count', 'list' => true, 'filters' => ['status' => ['warning', 'critical']]],
            ],
            'incidents', 'losses' => [
                ['type' => 'kpi', 'title' => 'Insiden tahun ini', 'dataset' => 'incidents', 'metric' => 'count', 'range' => ['year' => (int) now()->year]],
                ['type' => 'kpi', 'title' => 'Kerugian tahun ini', 'dataset' => 'incidents', 'metric' => 'sum:loss_amount', 'range' => ['year' => (int) now()->year]],
                ['type' => 'kpi', 'title' => 'Insiden terbuka', 'dataset' => 'incidents', 'metric' => 'count', 'filters' => ['status' => ['reported', 'investigating', 'corrective']]],
                ['type' => 'line', 'title' => 'Insiden per bulan (12 bulan)', 'dataset' => 'incidents', 'metric' => 'count', 'time' => ['field' => 'occurred_at', 'bucket' => 'month'], 'range' => ['last_months' => 12]],
                ['type' => 'pie', 'title' => 'Status insiden', 'dataset' => 'incidents', 'metric' => 'count', 'group' => 'status'],
                ['type' => 'bar', 'title' => 'Kerugian per tahun (loss event)', 'dataset' => 'losses', 'metric' => 'sum:amount', 'group' => 'year', 'sort' => 'desc'],
                ['type' => 'table', 'title' => 'Insiden dengan kerugian terbesar', 'dataset' => 'incidents', 'metric' => 'sum:loss_amount', 'list' => true],
            ],
            'controls' => [
                ['type' => 'kpi', 'title' => 'Kontrol', 'dataset' => 'controls', 'metric' => 'count'],
                ['type' => 'kpi', 'title' => 'Kontrol lemah', 'dataset' => 'controls', 'metric' => 'count', 'filters' => ['effectiveness' => ['Tidak Efektif', 'Sebagian Efektif']]],
                ['type' => 'kpi', 'title' => 'Uji jatuh tempo', 'dataset' => 'controls', 'metric' => 'count', 'filters' => ['test_due' => ['Jatuh tempo']]],
                ['type' => 'pie', 'title' => 'Tipe kontrol', 'dataset' => 'controls', 'metric' => 'count', 'group' => 'type'],
                ['type' => 'bar', 'title' => 'Efektivitas kontrol', 'dataset' => 'controls', 'metric' => 'count', 'group' => 'effectiveness'],
                ['type' => 'table', 'title' => 'Kontrol dengan uji jatuh tempo', 'dataset' => 'controls', 'metric' => 'count', 'list' => true, 'filters' => ['test_due' => ['Jatuh tempo']]],
            ],
            default => [
                ['type' => 'kpi', 'dataset' => $ds, 'metric' => 'count'],
                ['type' => 'pie', 'dataset' => $ds, 'metric' => 'count', 'group' => array_key_first($this->engine->datasets()->defs()[$ds]['dims'])],
                ['type' => 'line', 'dataset' => $ds, 'metric' => 'count', 'time' => ['bucket' => 'month'], 'range' => ['last_months' => 12]],
                ['type' => 'table', 'dataset' => $ds, 'metric' => 'count', 'list' => true],
            ],
        };
        if ($ds === 'risks' && !$all) {
            unset($t[3]);
        }
        return array_values($t);
    }
}

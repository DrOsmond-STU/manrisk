<?php

namespace App\Services\Ai;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Menjalankan spesifikasi widget dashboard (dari AI atau heuristik) secara aman:
 * setiap kunci divalidasi terhadap daftar putih Datasets, data diambil lewat query
 * bercakupan pengguna, lalu diagregasi di PHP. Tidak ada SQL atau ekspresi dari AI
 * yang dieksekusi.
 *
 * Widget: {type: kpi|bar|hbar|line|pie|table, title, dataset, metric: count|sum:f|avg:f|max:f|min:f,
 *          group?: dim, series?: dim, time?: {field, bucket: month|quarter|year},
 *          filters?: {dim: nilai|[nilai]}, range?: {last_months|year|from|to}, list?: bool, sort?: asc|desc, limit?: n}
 */
class AnalyticsEngine
{
    public const TYPES = ['kpi', 'bar', 'hbar', 'line', 'pie', 'table'];
    public const AGGS = ['count', 'sum', 'avg', 'max', 'min'];
    public const BUCKETS = ['month', 'quarter', 'year'];
    public const MAX_WIDGETS = 12;
    private const MAX_ROWS = 20000;

    private array $defs;
    private array $rowCache = [];

    public function __construct(private Datasets $data)
    {
        $this->defs = $data->defs();
    }

    public function datasets(): Datasets
    {
        return $this->data;
    }

    /** Validasi dan normalisasi; widget tidak valid dikembalikan null. */
    public function normalize(array $w): ?array
    {
        $ds = (string) ($w['dataset'] ?? '');
        if (!isset($this->defs[$ds])) {
            return null;
        }
        $def = $this->defs[$ds];
        $type = in_array($w['type'] ?? '', self::TYPES, true) ? $w['type'] : 'bar';
        [$agg, $field] = array_pad(explode(':', (string) ($w['metric'] ?? 'count'), 2), 2, null);
        if (!in_array($agg, self::AGGS, true) || ($agg !== 'count' && !isset($def['measures'][$field]))) {
            $agg = 'count';
            $field = null;
        }
        $group = isset($def['dims'][$w['group'] ?? '']) ? $w['group'] : null;
        $series = isset($def['dims'][$w['series'] ?? '']) && ($w['series'] ?? null) !== $group ? $w['series'] : null;
        $time = null;
        if (is_array($w['time'] ?? null)) {
            $tf = $w['time']['field'] ?? $def['default_date'];
            $time = ['field' => isset($def['dates'][$tf]) ? $tf : $def['default_date'], 'bucket' => in_array($w['time']['bucket'] ?? '', self::BUCKETS, true) ? $w['time']['bucket'] : 'month'];
        }
        $filters = [];
        foreach ((array) ($w['filters'] ?? []) as $k => $v) {
            if (isset($def['dims'][$k]) && (is_scalar($v) || is_array($v))) {
                $filters[$k] = array_values(array_filter(array_map(fn ($x) => mb_substr((string) $x, 0, 120), (array) $v), fn ($x) => $x !== ''));
            }
        }
        $range = [];
        $r = (array) ($w['range'] ?? []);
        if (!empty($r['last_months'])) {
            $range['last_months'] = max(1, min(60, (int) $r['last_months']));
        }
        if (!empty($r['year']) && (int) $r['year'] >= 2000 && (int) $r['year'] <= 2100) {
            $range['year'] = (int) $r['year'];
        }
        foreach (['from', 'to'] as $k) {
            if (!empty($r[$k]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $r[$k])) {
                $range[$k] = $r[$k];
            }
        }
        $list = (bool) ($w['list'] ?? false) && $type === 'table';
        if ($type === 'kpi') {
            $group = $series = $time = null;
        }
        if ($time) {
            $group = null;
        }
        if (in_array($type, ['bar', 'hbar', 'pie', 'line'], true) && !$group && !$time) {
            $type = 'kpi';
        }
        if ($type === 'pie') {
            $series = null;
        }
        if ($type === 'table' && !$group && !$time) {
            $list = true;
        }
        $title = trim(mb_substr(strip_tags((string) ($w['title'] ?? '')), 0, 120));
        $out = ['type' => $type, 'dataset' => $ds, 'metric' => $agg === 'count' ? 'count' : "$agg:$field", 'group' => $group, 'series' => $series, 'time' => $time, 'filters' => (object) $filters, 'range' => (object) $range,
            'list' => $list, 'sort' => ($w['sort'] ?? 'desc') === 'asc' ? 'asc' : 'desc', 'limit' => max(1, min(50, (int) ($w['limit'] ?? ($list ? 10 : 12))))];
        $out['title'] = $title ?: $this->autoTitle($out);
        return $out;
    }

    /** @return array{widgets: array, summary: string} */
    public function runAll(array $widgets): array
    {
        $out = [];
        foreach (array_slice($widgets, 0, self::MAX_WIDGETS) as $w) {
            $n = $this->normalize((array) $w);
            if ($n) {
                $out[] = $this->run($n);
            }
        }
        return ['widgets' => $out, 'summary' => $this->summary($out)];
    }

    public function run(array $w): array
    {
        $def = $this->defs[$w['dataset']];
        $rows = $this->filtered($w);
        [$agg, $field] = array_pad(explode(':', $w['metric'], 2), 2, null);
        $measure = $field ? $def['measures'][$field] : null;
        $format = $measure[2] ?? 'number';
        $metricLabel = $agg === 'count' ? 'Jumlah' : (['sum' => 'Total', 'avg' => 'Rata-rata', 'max' => 'Maksimum', 'min' => 'Minimum'][$agg] . ' ' . mb_strtolower($measure[0]));
        $calc = fn (Collection $c) => $this->aggregate($c, $agg, $measure);
        $base = ['spec' => $w, 'type' => $w['type'], 'title' => $w['title'], 'format' => $agg === 'count' ? 'number' : $format, 'metric_label' => $metricLabel, 'n' => $rows->count(), 'dataset_label' => $def['label']];

        if ($w['type'] === 'kpi') {
            return $base + ['value' => $calc($rows), 'insight' => "{$metricLabel} dari {$rows->count()} data " . mb_strtolower($def['label']) . '.'];
        }
        if ($w['list']) {
            return $base + $this->listRows($w, $def, $rows, $field);
        }
        // kelompok: dimensi atau ember waktu
        if ($w['time']) {
            $dateFn = $def['dates'][$w['time']['field']][1];
            $keyFn = fn ($r) => ($d = $dateFn($r)) ? $this->bucket($d, $w['time']['bucket']) : null;
            $groups = $rows->groupBy(fn ($r) => $keyFn($r) ?? '')->forget('');
            $labels = $this->timeLabels($groups->keys()->all(), $w);
        } else {
            $dimFn = $def['dims'][$w['group']][1];
            $groups = $rows->groupBy(fn ($r) => (string) $dimFn($r));
            $values = $groups->map($calc);
            $labels = ($w['sort'] === 'asc' ? $values->sort() : $values->sortDesc())->keys()->take($w['limit'])->values()->all();
            if ($order = $def['order'][$w['group']] ?? null) {
                usort($labels, fn ($a, $b) => $this->rank($order, $a) <=> $this->rank($order, $b));
            }
        }
        $series = [];
        if ($w['series']) {
            $sFn = $def['dims'][$w['series']][1];
            $names = $rows->groupBy(fn ($r) => (string) $sFn($r))->map->count()->sortDesc()->keys()->take(8)->all();
            if ($order = $def['order'][$w['series']] ?? null) {
                usort($names, fn ($a, $b) => $this->rank($order, $b) <=> $this->rank($order, $a)); // tumpukan: teringan → terparah
            }
            foreach ($names as $name) {
                $series[] = ['name' => (string) $name, 'data' => array_map(fn ($l) => $calc(($groups[$l] ?? collect())->filter(fn ($r) => (string) $sFn($r) === (string) $name)), $labels)];
            }
        } else {
            $series[] = ['name' => $metricLabel, 'data' => array_map(fn ($l) => $calc($groups[$l] ?? collect()), $labels)];
        }
        $display = $w['time'] ? array_map(fn ($k) => $this->bucketLabel($k, $w['time']['bucket']), $labels) : array_map('strval', $labels);
        $res = $base + ['labels' => $display, 'series' => $series, 'group_label' => $w['time'] ? $def['dates'][$w['time']['field']][0] : $def['dims'][$w['group']][0]];
        if ($w['type'] === 'table') {
            $res['columns'] = array_merge([$res['group_label']], array_column($series, 'name'));
            $res['rows'] = array_map(fn ($i) => array_merge([$display[$i]], array_map(fn ($s) => $s['data'][$i], $series)), array_keys($display));
        }
        $res['insight'] = $this->insight($res, (bool) $w['time']);
        return $res;
    }

    private function rank(array $order, $label): int
    {
        $i = array_search((string) $label, $order, true);
        return $i === false ? 999 : $i;
    }

    private function filtered(array $w): Collection
    {
        $def = $this->defs[$w['dataset']];
        $rows = $this->rowCache[$w['dataset']] ??= $def['query']()->limit(self::MAX_ROWS)->get();
        $filters = (array) $w['filters'];
        // bawaan: risiko yang sudah ditutup tidak dihitung kecuali status diminta eksplisit
        foreach ($def['exclude_default'] ?? [] as $dim => $raw) {
            if (!isset($filters[$dim])) {
                $keyFn = $def['dims'][$dim][2] ?? null;
                $rows = $rows->reject(fn ($r) => $keyFn && (string) $keyFn($r) === $raw);
            }
        }
        foreach ($filters as $dim => $vals) {
            if (!$vals) {
                continue;
            }
            $labelFn = $def['dims'][$dim][1];
            $keyFn = $def['dims'][$dim][2] ?? null;
            $wanted = array_map(fn ($v) => mb_strtolower(trim($v)), $vals);
            $rows = $rows->filter(fn ($r) => in_array(mb_strtolower((string) $labelFn($r)), $wanted, true) || ($keyFn && in_array(mb_strtolower((string) $keyFn($r)), $wanted, true)));
        }
        $range = (array) $w['range'];
        if ($range) {
            $field = $w['time']['field'] ?? $def['default_date'];
            $dateFn = $def['dates'][$field][1];
            $from = isset($range['last_months']) ? now()->startOfMonth()->subMonths($range['last_months'] - 1) : (isset($range['year']) ? Carbon::create($range['year'], 1, 1) : (isset($range['from']) ? Carbon::parse($range['from']) : null));
            $to = isset($range['year']) && !isset($range['last_months']) ? Carbon::create($range['year'], 12, 31)->endOfDay() : (isset($range['to']) ? Carbon::parse($range['to'])->endOfDay() : null);
            $rows = $rows->filter(function ($r) use ($dateFn, $from, $to) {
                $d = $dateFn($r);
                return $d && (!$from || $d->gte($from)) && (!$to || $d->lte($to));
            });
        }
        return $rows->values();
    }

    private function aggregate(Collection $c, string $agg, ?array $measure): float
    {
        if ($agg === 'count') {
            return (float) $c->count();
        }
        $vals = $c->map(fn ($r) => $measure[1]($r));
        return round((float) match ($agg) {
            'sum' => $vals->sum(),
            'avg' => $vals->count() ? $vals->avg() : 0,
            'max' => $vals->max() ?? 0,
            'min' => $vals->min() ?? 0,
        }, 2);
    }

    private function listRows(array $w, array $def, Collection $rows, ?string $field): array
    {
        $sortField = $field ?: $def['sort'];
        if ($sortField && isset($def['measures'][$sortField])) {
            $fn = $def['measures'][$sortField][1];
            $rows = $w['sort'] === 'asc' ? $rows->sortBy($fn) : $rows->sortByDesc($fn);
        }
        $rows = $rows->take($w['limit'])->values();
        $cols = $def['list'];
        $cell = function ($r, $key) use ($def) {
            if (str_starts_with($key, 'dim:')) {
                return (string) $def['dims'][substr($key, 4)][1]($r);
            }
            if (str_starts_with($key, 'date:')) {
                return $def['dates'][substr($key, 5)][1]($r)?->format('d/m/Y') ?? '—';
            }
            if (isset($def['measures'][$key])) {
                return $def['measures'][$key][1]($r);
            }
            return (string) ($r->{$key} ?? '');
        };
        $formats = array_map(fn ($k) => isset($def['measures'][$k]) ? $def['measures'][$k][2] : 'text', array_keys($cols));
        return ['columns' => array_values($cols), 'col_formats' => $formats, 'rows' => $rows->map(fn ($r) => array_map(fn ($k) => $cell($r, $k), array_keys($cols)))->all(),
            'links' => $rows->map(fn ($r) => $def['link']($r))->all(), 'insight' => "Menampilkan {$rows->count()} data teratas berdasarkan " . mb_strtolower($sortField && isset($def['measures'][$sortField]) ? $def['measures'][$sortField][0] : 'urutan bawaan') . '.'];
    }

    private function bucket(Carbon $d, string $b): string
    {
        return match ($b) {
            'year' => $d->format('Y'),
            'quarter' => $d->format('Y') . '-Q' . $d->quarter,
            default => $d->format('Y-m'),
        };
    }

    /** Lengkapi ember kosong di antara awal–akhir agar grafik tren kontinu (maks. 60 titik). */
    private function timeLabels(array $keys, array $w): array
    {
        sort($keys);
        $b = $w['time']['bucket'];
        $range = (array) $w['range'];
        if (isset($range['last_months'])) {
            $start = now()->startOfMonth()->subMonths($range['last_months'] - 1);
            $end = now()->startOfMonth();
        } elseif ($keys) {
            $start = $this->bucketStart($keys[0], $b);
            $end = $this->bucketStart(end($keys), $b);
        } else {
            return [];
        }
        $out = [];
        $step = ['month' => 1, 'quarter' => 3, 'year' => 12][$b];
        for ($d = $start->copy(); $d->lte($end) && count($out) < 60; $d->addMonths($step)) {
            $out[] = $this->bucket($d, $b);
        }
        return array_values(array_unique($out));
    }

    private function bucketStart(string $key, string $b): Carbon
    {
        if ($b === 'year') {
            return Carbon::create((int) $key, 1, 1);
        }
        if ($b === 'quarter') {
            [$y, $q] = explode('-Q', $key);
            return Carbon::create((int) $y, ((int) $q - 1) * 3 + 1, 1);
        }
        return Carbon::createFromFormat('Y-m-d', $key . '-01')->startOfMonth();
    }

    private function bucketLabel(string $key, string $b): string
    {
        if ($b === 'month') {
            return Carbon::createFromFormat('Y-m-d', $key . '-01')->translatedFormat('M Y');
        }
        return str_replace('-Q', ' T', $key);
    }

    private function autoTitle(array $w): string
    {
        $def = $this->defs[$w['dataset']];
        [$agg, $field] = array_pad(explode(':', $w['metric'], 2), 2, null);
        $m = $agg === 'count' ? 'Jumlah ' . mb_strtolower($def['label']) : (['sum' => 'Total', 'avg' => 'Rata-rata', 'max' => 'Maksimum', 'min' => 'Minimum'][$agg] . ' ' . mb_strtolower($def['measures'][$field][0]));
        if ($w['list']) {
            return 'Daftar ' . mb_strtolower($def['label']);
        }
        if ($w['time']) {
            return $m . ' per ' . ['month' => 'bulan', 'quarter' => 'triwulan', 'year' => 'tahun'][$w['time']['bucket']];
        }
        return $w['group'] ? $m . ' per ' . mb_strtolower($def['dims'][$w['group']][0]) : $m;
    }

    public static function fmt(float $v, string $format): string
    {
        return match ($format) {
            'money' => 'Rp ' . self::short($v),
            'percent' => rtrim(rtrim(number_format($v, 1, ',', '.'), '0'), ',') . '%',
            default => rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ','),
        };
    }

    private static function short(float $v): string
    {
        $a = abs($v);
        return $a >= 1e12 ? number_format($v / 1e12, 1, ',', '.') . ' T' : ($a >= 1e9 ? number_format($v / 1e9, 1, ',', '.') . ' M' : ($a >= 1e6 ? number_format($v / 1e6, 1, ',', '.') . ' jt' : number_format($v, 0, ',', '.')));
    }

    /** Wawasan singkat per widget, dihitung dari data (bukan dari AI). */
    private function insight(array $r, bool $isTime): string
    {
        $data = $r['series'][0]['data'] ?? [];
        if (!$data || !array_sum(array_map('abs', $data))) {
            return 'Belum ada data untuk kriteria ini.';
        }
        if (count($r['series']) > 1) {
            $tot = [];
            foreach ($r['series'] as $s) {
                $tot[$s['name']] = array_sum($s['data']);
            }
            arsort($tot);
            $top = array_key_first($tot);
            return "Kelompok {$top} paling dominan (" . self::fmt($tot[$top], $r['format']) . ').';
        }
        if ($isTime) {
            $first = $data[0];
            $last = end($data);
            $peak = array_search(max($data), $data, true);
            $dir = $last > $first ? 'naik' : ($last < $first ? 'turun' : 'tetap');
            return "Tren {$dir} dari " . self::fmt($first, $r['format']) . ' (' . $r['labels'][0] . ') ke ' . self::fmt($last, $r['format']) . ' (' . end($r['labels']) . '); puncak pada ' . $r['labels'][$peak] . '.';
        }
        $i = array_search(max($data), $data, true);
        $sum = array_sum($data);
        $share = $sum > 0 && !str_starts_with($r['metric_label'], 'Rata') ? ', ' . round($data[$i] / $sum * 100) . '% dari total' : '';
        return "Tertinggi: {$r['labels'][$i]} (" . self::fmt($data[$i], $r['format']) . "{$share}).";
    }

    public function summary(array $results): string
    {
        $lines = [];
        foreach ($results as $r) {
            if ($r['type'] === 'kpi') {
                $lines[] = "{$r['title']}: " . self::fmt((float) $r['value'], $r['format']) . '.';
            } elseif (!empty($r['insight']) && !str_starts_with($r['insight'], 'Menampilkan') && !str_starts_with($r['insight'], 'Belum')) {
                $lines[] = "{$r['title']} — {$r['insight']}";
            }
        }
        return implode(' ', array_slice($lines, 0, 6));
    }
}

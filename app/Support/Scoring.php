<?php

namespace App\Support;

use App\Models\CriteriaVersion;
use App\Models\Risk;
use App\Models\RiskCategory;

/**
 * Satu-satunya tempat perhitungan skor, level, dan status evaluasi risiko (spesifikasi §5).
 * Matriks dan ambang diambil dari CriteriaVersion aktif; bila belum ada, memakai bawaan.
 */
class Scoring
{
    /** Label skema standar (4 level); kunci tetap dipakai untuk kompatibilitas. */
    public const LEVELS = ['low' => 'Rendah', 'medium' => 'Sedang', 'high' => 'Tinggi', 'very_high' => 'Sangat Tinggi'];
    public const EVALUATIONS = ['acceptable' => 'Dapat Diterima', 'monitor' => 'Dipantau', 'treat' => 'Perlu Penanganan', 'escalate' => 'Perlu Eskalasi', 'critical' => 'Kritis'];
    /** Urutan seluruh kunci level dari dua skema (low_medium hanya dipakai skema BUMN). */
    public const LEVEL_ORDER = ['low' => 0, 'low_medium' => 1, 'medium' => 2, 'high' => 3, 'very_high' => 4];
    /**
     * Skema level: standar (4 level) dan BUMN (5 level: Low, Low to Moderate, Moderate, Moderate to High, High)
     * sesuai peta risiko 5x5 dalam petunjuk teknis manajemen risiko BUMN (PER-2/MBU/03/2023).
     */
    public const SCHEMES = [
        'standard' => ['label' => 'Standar (4 level)', 'levels' => self::LEVELS],
        'bumn' => ['label' => 'BUMN — PER-2/MBU/03/2023 (5 level)', 'levels' => ['low' => 'Low', 'low_medium' => 'Low to Moderate', 'medium' => 'Moderate', 'high' => 'Moderate to High', 'very_high' => 'High']],
    ];

    private ?array $matrix = null;
    private array $thresholds = ['escalate' => 16, 'critical' => 20];
    private string $scheme = 'standard';

    public function __construct(?CriteriaVersion $criteria = null)
    {
        $criteria ??= CriteriaVersion::current();
        if ($criteria) {
            $this->matrix = $criteria->matrix ?: null;
            $this->thresholds = array_merge($this->thresholds, $criteria->thresholds ?: []);
            $this->scheme = isset(self::SCHEMES[$criteria->scheme]) ? $criteria->scheme : 'standard';
        }
    }

    public static function defaultMatrix(string $scheme = 'standard'): array
    {
        $m = [];
        for ($l = 1; $l <= 5; $l++) {
            for ($i = 1; $i <= 5; $i++) {
                $m["$l-$i"] = self::levelFromScore($l * $i, $scheme);
            }
        }
        return $m;
    }

    /** Level bawaan dari skor; dipakai bila matriks belum ditetapkan (dapat diubah di menu Kriteria). */
    public static function levelFromScore(int $score, string $scheme = 'standard'): string
    {
        if ($scheme === 'bumn') {
            return $score >= 20 ? 'very_high' : ($score >= 15 ? 'high' : ($score >= 10 ? 'medium' : ($score >= 5 ? 'low_medium' : 'low')));
        }
        return $score >= 16 ? 'very_high' : ($score >= 10 ? 'high' : ($score >= 5 ? 'medium' : 'low'));
    }

    /** Skema kriteria aktif organisasi pengguna saat ini. */
    public static function currentScheme(): string
    {
        return once(function () {
            $scheme = CriteriaVersion::current()?->scheme;
            return isset(self::SCHEMES[$scheme]) ? $scheme : 'standard';
        });
    }

    /** Label level skema aktif, urut dari terendah. */
    public static function levels(?string $scheme = null): array
    {
        return self::SCHEMES[$scheme ?? self::currentScheme()]['levels'];
    }

    public function scheme(): string
    {
        return $this->scheme;
    }

    /** Level dari skor menurut kriteria instans ini (matriks bila ada sel yang cocok, selain itu pita skor skema). */
    public function levelForScore(int $score): string
    {
        foreach ($this->matrix ?? [] as $key => $lv) {
            [$l, $i] = array_map('intval', explode('-', $key));
            if ($l * $i === $score) {
                return $lv;
            }
        }
        return self::levelFromScore($score, $this->scheme);
    }

    public function score(int $l, int $i): int
    {
        return max(1, min(5, $l)) * max(1, min(5, $i));
    }

    public function level(int $l, int $i): string
    {
        $key = "$l-$i";
        return $this->matrix[$key] ?? self::levelFromScore($this->score($l, $i), $this->scheme);
    }

    /** Status evaluasi residual terhadap appetite/tolerance kategori (urutan cek §5.3). */
    public function evaluate(int $score, int $appetite, int $tolerance): string
    {
        if ($score >= $this->thresholds['critical']) {
            return 'critical';
        }
        if ($score >= $this->thresholds['escalate']) {
            return 'escalate';
        }
        if ($score > $tolerance) {
            return 'treat';
        }
        if ($score > $appetite) {
            return 'monitor';
        }
        return 'acceptable';
    }

    /** Nilai dampak dari beberapa dimensi = dimensi tertinggi yang terisi. */
    public static function impactFromDims(?array $dims, int $fallback): int
    {
        $vals = array_filter(array_map('intval', $dims ?? []), fn ($v) => $v >= 1 && $v <= 5);
        return $vals ? max($vals) : $fallback;
    }

    /** Hitung ulang seluruh field turunan pada risiko (tanpa menyimpan). */
    public function apply(Risk $risk, ?RiskCategory $category = null): Risk
    {
        $category ??= $risk->category;
        $risk->inherent_i = self::impactFromDims($risk->inherent_dims, (int) $risk->inherent_i);
        $risk->residual_i = self::impactFromDims($risk->residual_dims, (int) $risk->residual_i);
        $risk->inherent_score = $this->score((int) $risk->inherent_l, (int) $risk->inherent_i);
        $risk->residual_score = $this->score((int) $risk->residual_l, (int) $risk->residual_i);
        $risk->target_score = $this->score((int) $risk->target_l, (int) $risk->target_i);
        $risk->residual_level = $this->level((int) $risk->residual_l, (int) $risk->residual_i);
        $risk->evaluation = $this->evaluate($risk->residual_score, (int) ($category?->appetite ?? 6), (int) ($category?->tolerance ?? 9));
        if ($risk->previous_score !== null) {
            $risk->trend = $risk->residual_score > $risk->previous_score ? 'up' : ($risk->residual_score < $risk->previous_score ? 'down' : 'flat');
        }
        return $risk;
    }

    /** Proyeksi residual setelah action plan aktif selesai. */
    public function projected(Risk $risk): array
    {
        $dl = 0;
        $di = 0;
        foreach ($risk->actionPlans as $a) {
            if (!$a->cancelled_at) {
                $dl += (int) $a->expected_dl;
                $di += (int) $a->expected_di;
            }
        }
        $l = max(1, $risk->residual_l - $dl);
        $i = max(1, $risk->residual_i - $di);
        return ['l' => $l, 'i' => $i, 'score' => $this->score($l, $i), 'level' => $this->level($l, $i)];
    }

    public static function levelLabel(string $level): string
    {
        return self::levels()[$level] ?? self::SCHEMES['bumn']['levels'][$level] ?? self::LEVELS[$level] ?? $level;
    }
}

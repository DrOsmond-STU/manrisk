<?php

namespace App\Support;

use App\Models\RiskCategory;
use Illuminate\Support\Collection;

/**
 * Pemakaian risk limit (batas eksposur residual dalam Rupiah) per kategori taksonomi.
 * Eksposur kategori = jumlah eksposur residual risiko aktif pada kategori itu dan seluruh turunannya (agregasi T3 → T2 → T1).
 */
class RiskLimits
{
    public const NEAR = 0.8; // ≥ 80 % limit: mendekati batas

    /** @param Collection $risks risiko aktif (butuh category_id, residual_exposure, inherent_exposure) */
    public static function usage(Collection $risks): Collection
    {
        $byCat = $risks->groupBy('category_id');
        return RiskCategory::tree()->map(function ($c) use ($byCat) {
            $rs = collect($c->descendant_ids)->flatMap(fn ($id) => $byCat->get($id, collect()));
            $exposure = (float) $rs->sum('residual_exposure');
            $limit = $c->risk_limit !== null ? (float) $c->risk_limit : null;
            $pct = $limit ? round($exposure / $limit * 100, 1) : null;
            return ['id' => $c->id, 'name' => $c->name, 'path' => $c->path, 'depth' => $c->depth, 'bumn_code' => $c->bumn_code, 'n' => $rs->count(),
                'inherent' => (float) $rs->sum('inherent_exposure'), 'exposure' => $exposure, 'limit' => $limit, 'pct' => $pct,
                'status' => $limit === null ? 'none' : ($exposure > $limit ? 'breach' : ($exposure >= $limit * self::NEAR ? 'near' : 'ok'))];
        })->values();
    }

    /** Ringkasan organisasi: total eksposur vs risk limit/kapasitas yang ditetapkan di pengaturan organisasi. */
    public static function total(Collection $risks, array $settings): array
    {
        $exposure = (float) $risks->sum('residual_exposure');
        $limit = isset($settings['risk_limit_total']) && $settings['risk_limit_total'] !== '' ? (float) $settings['risk_limit_total'] : null;
        $capacity = isset($settings['risk_capacity']) && $settings['risk_capacity'] !== '' ? (float) $settings['risk_capacity'] : null;
        return ['inherent' => (float) $risks->sum('inherent_exposure'), 'exposure' => $exposure, 'target' => (float) $risks->sum('target_exposure'),
            'limit' => $limit, 'capacity' => $capacity, 'pct' => $limit ? round($exposure / $limit * 100, 1) : null,
            'status' => $limit === null ? 'none' : ($exposure > $limit ? 'breach' : ($exposure >= $limit * self::NEAR ? 'near' : 'ok')),
            'quantified' => $risks->whereNotNull('residual_exposure')->count(), 'total' => $risks->count()];
    }
}

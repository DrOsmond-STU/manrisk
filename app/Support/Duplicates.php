<?php

namespace App\Support;

use App\Models\Risk;
use App\Models\User;

/** Deteksi kemiripan pernyataan risiko di unit yang sama (spesifikasi F-IDN-07). */
class Duplicates
{
    public const THRESHOLD = 80;

    public static function normalize(string $t): string
    {
        $t = mb_strtolower(strip_tags($t));
        $t = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $t);
        return trim(preg_replace('/\s+/', ' ', $t));
    }

    public static function score(string $a, string $b): float
    {
        $a = self::normalize($a);
        $b = self::normalize($b);
        if ($a === '' || $b === '') {
            return 0;
        }
        similar_text($a, $b, $pct);
        // kombinasi dengan Jaccard kata agar tahan perbedaan urutan
        $wa = array_unique(explode(' ', $a));
        $wb = array_unique(explode(' ', $b));
        $jac = count(array_intersect($wa, $wb)) / max(1, count(array_unique(array_merge($wa, $wb)))) * 100;
        return round(max($pct, $jac), 1);
    }

    public static function find(int $unitId, string $text, ?int $except, User $user): array
    {
        if ($user->isUnitScoped() && !$user->canAccessUnit($unitId)) {
            return [];
        }
        return Risk::where('unit_id', $unitId)->where('status', '!=', 'closed')->when($except, fn ($q) => $q->where('id', '!=', $except))
            ->get(['id', 'code', 'name', 'cause', 'event', 'impact'])
            ->map(fn ($r) => ['id' => $r->id, 'code' => $r->code, 'name' => $r->name, 'score' => max(self::score($text, $r->name), self::score($text, $r->event), self::score($text, "{$r->cause} {$r->event} {$r->impact}"))])
            ->filter(fn ($r) => $r['score'] >= self::THRESHOLD)->sortByDesc('score')->take(5)->values()->all();
    }
}

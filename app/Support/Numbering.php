<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Penomoran ID berurutan per organisasi, tidak pernah dipakai ulang (spesifikasi §5.8).
 * Pola bawaan: PREFIX-TAHUN-URUT (R-2026-014). Dikunci dengan transaksi agar aman dari balapan.
 */
class Numbering
{
    public static function next(string $modelClass, string $prefix, ?int $organizationId = null, bool $withYear = true): string
    {
        $organizationId ??= auth()->user()?->organization_id;
        $year = now()->format('Y');
        $base = $withYear ? "$prefix-$year-" : "$prefix-";

        return DB::transaction(function () use ($modelClass, $base, $organizationId) {
            /** @var Model $modelClass */
            $query = $modelClass::withoutGlobalScopes()->where('organization_id', $organizationId)->where('code', 'like', $base . '%');
            if (in_array('Illuminate\\Database\\Eloquent\\SoftDeletes', class_uses_recursive($modelClass), true)) {
                $query->withTrashed();
            }
            $last = $query->lockForUpdate()->orderByDesc('code')->value('code');
            $n = $last ? ((int) substr($last, strlen($base))) + 1 : 1;
            return $base . str_pad((string) $n, 3, '0', STR_PAD_LEFT);
        });
    }
}

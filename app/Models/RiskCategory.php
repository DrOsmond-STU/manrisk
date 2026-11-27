<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RiskCategory extends Model
{
    use HasFactory, BelongsToOrganization, Auditable, SoftDeletes;

    protected $fillable = [
        'parent_id',
        'name',
        'name_en',
        'bumn_code',
        'description',
        'appetite',
        'tolerance',
        'risk_limit',
        'sort',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'risk_limit' => 'float',
    ];

    /** Tingkat taksonomi (PER-2/MBU/03/2023): T1 Tema Risiko, T2 Kategori Risiko, T3 Kelompok Peristiwa Risiko. */
    public const TIERS = [1 => 'T1 · Tema Risiko', 2 => 'T2 · Kategori Risiko', 3 => 'T3 · Kelompok Peristiwa Risiko'];
    public const MAX_DEPTH = 3;

    /**
     * Seluruh kategori organisasi dalam urutan pohon, masing-masing diberi `depth` (1-3), `path` (Tema › Kategori › Peristiwa)
     * dan `descendant_ids` (termasuk dirinya) untuk agregasi.
     */
    public static function tree(?\Illuminate\Support\Collection $all = null): \Illuminate\Support\Collection
    {
        $all ??= static::orderBy('sort')->orderBy('name')->get();
        $ids = $all->pluck('id')->flip();
        // akar = tanpa induk, atau induknya tidak ada (dihapus / beda organisasi)
        $byParent = $all->groupBy(fn ($c) => $c->parent_id && isset($ids[$c->parent_id]) ? $c->parent_id : 0);
        $out = collect();
        $walk = function ($parentId, $depth, $path) use (&$walk, $byParent, $out) {
            foreach ($byParent->get($parentId, collect()) as $c) {
                $c->depth = $depth;
                $c->path = $path ? "{$path} › {$c->name}" : $c->name;
                $out->push($c);
                if ($depth < 10) {
                    $walk($c->id, $depth + 1, $c->path);
                }
            }
        };
        $walk(0, 1, '');
        foreach ($out as $c) {
            $c->descendant_ids = static::collectDescendants($c->id, $byParent);
        }
        return $out;
    }

    private static function collectDescendants(int $id, $byParent, int $guard = 0): array
    {
        $ids = [$id];
        if ($guard > 10) {
            return $ids;
        }
        foreach ($byParent->get($id, collect()) as $child) {
            $ids = array_merge($ids, static::collectDescendants($child->id, $byParent, $guard + 1));
        }
        return $ids;
    }

    /** Id kategori ini beserta seluruh turunannya (untuk filter agregasi T1/T2). */
    public function descendantIds(): array
    {
        return static::tree()->firstWhere('id', $this->id)?->descendant_ids ?? [$this->id];
    }

    public function depth(): int
    {
        $d = 1;
        $p = $this->parent;
        while ($p && $d < 10) {
            $d++;
            $p = $p->parent;
        }
        return $d;
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(RiskCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(RiskCategory::class, 'parent_id');
    }

    public function risks(): HasMany
    {
        return $this->hasMany(Risk::class, 'category_id');
    }
}

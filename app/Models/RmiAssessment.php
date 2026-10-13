<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Penilaian Risk Maturity Index (PER-2/MBU/03/2023): penilaian internal setiap tahun dan penilaian independen
 * paling sedikit sekali dalam tiga tahun. Indeks 1,0–5,0 = rata-rata tertimbang skor parameter, dipetakan ke fase.
 */
class RmiAssessment extends Model
{
    use BelongsToOrganization, Auditable;

    protected $fillable = ['year', 'type', 'assessor', 'assessed_at', 'status', 'score', 'phase', 'note', 'created_by', 'finalized_at'];

    protected $casts = ['assessed_at' => 'date', 'finalized_at' => 'datetime', 'score' => 'float'];

    public function scores(): HasMany
    {
        return $this->hasMany(RmiScore::class, 'assessment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Fase maturitas untuk indeks tertentu (batas fase dari config manrisk.rmi.phases). */
    public static function phaseFor(?float $score): ?string
    {
        if ($score === null) {
            return null;
        }
        foreach (config('manrisk.rmi.phases') as $key => $p) {
            if ($score <= $p['max']) {
                return $key;
            }
        }
        return array_key_last(config('manrisk.rmi.phases'));
    }

    /** Hitung indeks per dimensi dan keseluruhan dari skor yang terisi. */
    public function compute(): array
    {
        $scores = $this->scores()->with('parameter')->get()->filter(fn ($s) => $s->parameter && $s->score);
        $dims = [];
        foreach (config('manrisk.rmi.dimensions') as $key => $label) {
            $set = $scores->filter(fn ($s) => $s->parameter->dimension === $key);
            $w = $set->sum(fn ($s) => $s->parameter->weight);
            $dims[$key] = $w > 0 ? round($set->sum(fn ($s) => $s->score * $s->parameter->weight) / $w, 2) : null;
        }
        $w = $scores->sum(fn ($s) => $s->parameter->weight);
        $index = $w > 0 ? round($scores->sum(fn ($s) => $s->score * $s->parameter->weight) / $w, 2) : null;
        return ['index' => $index, 'phase' => self::phaseFor($index), 'dimensions' => $dims, 'scored' => $scores->count(), 'total' => $this->scores()->count()];
    }

    public function refreshScore(): void
    {
        $c = $this->compute();
        $this->forceFill(['score' => $c['index'], 'phase' => $c['phase']])->save();
    }
}

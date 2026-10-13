<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Target risiko residual per triwulan dalam tahun RKAP (PER-2/MBU/03/2023: perencanaan risiko menyatu dengan RKAP). */
class RiskQuarterTarget extends Model
{
    protected $fillable = ['risk_id', 'year', 'quarter', 'target_l', 'target_i', 'target_score', 'target_exposure'];

    protected $casts = ['target_exposure' => 'float'];

    public function risk(): BelongsTo
    {
        return $this->belongsTo(Risk::class);
    }
}

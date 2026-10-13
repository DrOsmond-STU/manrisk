<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RmiScore extends Model
{
    protected $fillable = ['assessment_id', 'parameter_id', 'score', 'evidence'];

    protected $casts = ['score' => 'integer'];

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(RmiParameter::class, 'parameter_id');
    }
}

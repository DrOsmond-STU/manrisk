<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kri extends Model
{
    use HasFactory, BelongsToOrganization, Auditable, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'unit',
        'risk_id',
        'owner_id',
        'source',
        'frequency',
        'direction',
        'threshold_warn',
        'threshold_crit',
        'decimals',
        'last_value',
        'status',
        'active',
    ];

    protected $casts = [
        'threshold_warn' => 'float',
        'threshold_crit' => 'float',
        'last_value' => 'float',
        'active' => 'boolean',
    ];
    public function statusFor(?float $value): string
    {
        if ($value === null) {
            return 'normal';
        }
        if ($this->direction === 'down_bad') {
            return $value < $this->threshold_crit ? 'critical' : ($value < $this->threshold_warn ? 'warning' : 'normal');
        }
        return $value > $this->threshold_crit ? 'critical' : ($value > $this->threshold_warn ? 'warning' : 'normal');
    }


    public function risk(): BelongsTo
    {
        return $this->belongsTo(Risk::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(KriValue::class);
    }

    /** Improvement yang bersumber dari objek ini (subject_type/subject_id). */
    public function improvements(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Improvement::class, 'subject');
    }
}

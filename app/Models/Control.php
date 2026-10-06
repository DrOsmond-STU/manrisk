<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Control extends Model
{
    use HasFactory, BelongsToOrganization, Auditable, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'objective',
        'description',
        'owner_id',
        'unit_id',
        'frequency',
        'type',
        'mode',
        'design_eff',
        'operating_eff',
        'last_tested_at',
        'next_test_at',
        'active',
        'created_by',
    ];

    protected $casts = [
        'last_tested_at' => 'date',
        'next_test_at' => 'date',
        'active' => 'boolean',
    ];
    public function overallEffectiveness(): ?int
    {
        if ($this->design_eff === null || $this->operating_eff === null) {
            return null;
        }
        return min($this->design_eff, $this->operating_eff);
    }


    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'unit_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(ControlAssessment::class);
    }

    public function risks(): BelongsToMany
    {
        return $this->belongsToMany(Risk::class, 'control_risk');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'subject');
    }

    /** Improvement yang bersumber dari objek ini (subject_type/subject_id). */
    public function improvements(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Improvement::class, 'subject');
    }
}

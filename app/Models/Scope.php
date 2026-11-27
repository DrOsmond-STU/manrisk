<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Scope extends Model
{
    use HasFactory, BelongsToOrganization, Auditable, SoftDeletes;

    protected $fillable = [
        'name',
        'objective',
        'boundaries',
        'period',
        'unit_id',
        'area',
        'created_by',
        'kind',
        'kks_type',
        'transaction_value',
        'decision_organ',
        'decision_date',
        'conclusion',
    ];

    protected $casts = ['transaction_value' => 'float', 'decision_date' => 'date'];

    public function risks(): HasMany
    {
        return $this->hasMany(Risk::class, 'scope_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'unit_id');
    }

    public function factors(): HasMany
    {
        return $this->hasMany(ContextFactor::class, 'scope_id');
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class, 'scope_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

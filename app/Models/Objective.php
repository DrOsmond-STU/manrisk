<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Objective extends Model
{
    use HasFactory, BelongsToOrganization, Auditable, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'kpi',
        'period',
        'sort',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    public function risks(): HasMany
    {
        return $this->hasMany(Risk::class);
    }
}

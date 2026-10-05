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
        'description',
        'appetite',
        'tolerance',
        'sort',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

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

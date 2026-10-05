<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CriteriaVersion extends Model
{
    use HasFactory, BelongsToOrganization, Auditable;

    protected $fillable = [
        'version',
        'effective_from',
        'likelihood',
        'impact',
        'dimensions',
        'matrix',
        'thresholds',
        'active',
        'created_by',
    ];

    protected $casts = [
        'likelihood' => 'array',
        'impact' => 'array',
        'dimensions' => 'array',
        'matrix' => 'array',
        'thresholds' => 'array',
        'active' => 'boolean',
        'effective_from' => 'date',
    ];
    public static function current(): ?self
    {
        return static::where('active', true)->orderByDesc('version')->first();
    }

    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

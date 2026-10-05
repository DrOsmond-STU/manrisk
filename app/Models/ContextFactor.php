<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContextFactor extends Model
{
    use HasFactory, BelongsToOrganization, Auditable;

    protected $fillable = [
        'scope_id',
        'kind',
        'factor',
        'condition',
        'nature',
    ];

    public function scope(): BelongsTo
    {
        return $this->belongsTo(Scope::class);
    }
}

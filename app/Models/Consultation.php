<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consultation extends Model
{
    use HasFactory, BelongsToOrganization, Auditable;

    protected $fillable = [
        'scope_id',
        'title',
        'held_on',
        'participants',
        'decisions',
        'status',
    ];

    protected $casts = [
        'held_on' => 'date',
    ];

    public function scope(): BelongsTo
    {
        return $this->belongsTo(Scope::class);
    }
}

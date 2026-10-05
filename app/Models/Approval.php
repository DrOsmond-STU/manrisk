<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Approval extends Model
{
    use HasFactory, BelongsToOrganization, Auditable;

    protected $fillable = [
        'code',
        'type',
        'subject_type',
        'subject_id',
        'requester_id',
        'current_step',
        'total_steps',
        'status',
        'note',
        'payload',
        'decided_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'decided_at' => 'datetime',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}

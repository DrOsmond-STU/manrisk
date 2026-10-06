<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Incident extends Model
{
    use HasFactory, BelongsToOrganization, Auditable, SoftDeletes;

    protected $fillable = [
        'code',
        'occurred_at',
        'location',
        'risk_id',
        'unit_id',
        'title',
        'chronology',
        'cause',
        'impact',
        'loss_amount',
        'loss_type',
        'response',
        'corrective_action',
        'status',
        'reported_by',
        'closed_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'loss_amount' => 'decimal:2',
        'closed_at' => 'datetime',
    ];

    public function risk(): BelongsTo
    {
        return $this->belongsTo(Risk::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'unit_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function lossEvents(): HasMany
    {
        return $this->hasMany(LossEvent::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'subject');
    }

    public function lessons(): MorphMany
    {
        return $this->morphMany(Lesson::class, 'subject');
    }

    /** Improvement yang bersumber dari objek ini (subject_type/subject_id). */
    public function improvements(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Improvement::class, 'subject');
    }
}

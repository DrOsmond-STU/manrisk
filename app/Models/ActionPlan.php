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

class ActionPlan extends Model
{
    use HasFactory, BelongsToOrganization, Auditable, SoftDeletes;

    protected $fillable = [
        'code',
        'risk_id',
        'title',
        'description',
        'pic_id',
        'unit_id',
        'budget',
        'priority',
        'start_date',
        'due_date',
        'progress',
        'expected_dl',
        'expected_di',
        'completed_at',
        'verified_at',
        'verified_by',
        'cancelled_at',
        'cancel_reason',
        'created_by',
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'start_date' => 'date',
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'verified_at' => 'datetime',
    ];
    /** Status dihitung: cancelled | done | verify (menunggu verifikasi) | overdue | todo | running */
    public function computedStatus(): string
    {
        if ($this->cancelled_at) {
            return 'cancelled';
        }
        if ($this->progress >= 100) {
            return (config('manrisk.plan_completion_verification') && !$this->verified_at) ? 'verify' : 'done';
        }
        if ($this->due_date && $this->due_date->lt(now()->startOfDay())) {
            return 'overdue';
        }
        return (int) $this->progress === 0 ? 'todo' : 'running';
    }


    public function risk(): BelongsTo
    {
        return $this->belongsTo(Risk::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'unit_id');
    }

    public function progressLog(): HasMany
    {
        return $this->hasMany(ActionProgress::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'subject');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}

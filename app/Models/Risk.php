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

class Risk extends Model
{
    use HasFactory, BelongsToOrganization, Auditable, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'unit_id',
        'objective_id',
        'process_id',
        'category_id',
        'owner_id',
        'cause',
        'event',
        'impact',
        'source_type',
        'source_kind',
        'existing_controls',
        'treatment',
        'treatment_note',
        'status',
        'due_date',
        'criteria_version_id',
        'inherent_l',
        'inherent_i',
        'inherent_dims',
        'residual_l',
        'residual_i',
        'residual_dims',
        'target_l',
        'target_i',
        'inherent_score',
        'residual_score',
        'target_score',
        'residual_level',
        'evaluation',
        'trend',
        'previous_score',
        'version',
        'submitted_at',
        'approved_at',
        'closed_at',
        'closed_reason',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'due_date' => 'date',
        'inherent_dims' => 'array',
        'residual_dims' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];
    public function statement(): string
    {
        return "Karena {$this->cause}, dapat terjadi {$this->event}, sehingga menyebabkan {$this->impact}.";
    }


    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'unit_id');
    }

    public function objective(): BelongsTo
    {
        return $this->belongsTo(Objective::class);
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RiskCategory::class, 'category_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(RiskVersion::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(RiskSnapshot::class);
    }

    public function actionPlans(): HasMany
    {
        return $this->hasMany(ActionPlan::class);
    }

    public function kris(): HasMany
    {
        return $this->hasMany(Kri::class);
    }

    public function improvements(): HasMany
    {
        return $this->hasMany(Improvement::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function controls(): BelongsToMany
    {
        return $this->belongsToMany(Control::class, 'control_risk');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'subject');
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'subject');
    }

    public function lessons(): MorphMany
    {
        return $this->morphMany(Lesson::class, 'subject');
    }
}

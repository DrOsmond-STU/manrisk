<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'risk_id',
        'version',
        'inherent_l',
        'inherent_i',
        'residual_l',
        'residual_i',
        'target_l',
        'target_i',
        'snapshot',
        'note',
        'created_by',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'approved_at' => 'datetime',
    ];

    public function risk(): BelongsTo
    {
        return $this->belongsTo(Risk::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

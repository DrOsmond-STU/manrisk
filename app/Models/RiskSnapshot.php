<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskSnapshot extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'risk_id',
        'period',
        'inherent_score',
        'residual_score',
        'level',
        'status',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
    public $timestamps = false;


    public function risk(): BelongsTo
    {
        return $this->belongsTo(Risk::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LossEvent extends Model
{
    use HasFactory, BelongsToOrganization, Auditable;

    protected $fillable = [
        'incident_id',
        'category_id',
        'year',
        'risk_name',
        'event',
        'amount',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RiskCategory::class, 'category_id');
    }
}

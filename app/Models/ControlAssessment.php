<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ControlAssessment extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'control_id',
        'tested_at',
        'tester_id',
        'design_eff',
        'operating_eff',
        'note',
    ];

    protected $casts = [
        'tested_at' => 'date',
    ];

    public function control(): BelongsTo
    {
        return $this->belongsTo(Control::class);
    }

    public function tester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tester_id');
    }
}

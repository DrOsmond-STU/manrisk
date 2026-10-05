<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KriValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'kri_id',
        'period',
        'value',
        'entered_by',
        'source_ref',
    ];

    protected $casts = [
        'period' => 'date',
        'value' => 'float',
    ];

    public function kri(): BelongsTo
    {
        return $this->belongsTo(Kri::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}

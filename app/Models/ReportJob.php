<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportJob extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'user_id',
        'type',
        'format',
        'params',
        'status',
        'path',
        'error',
        'finished_at',
    ];

    protected $casts = [
        'params' => 'array',
        'finished_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

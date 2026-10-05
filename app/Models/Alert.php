<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'type',
        'severity',
        'subject_type',
        'subject_id',
        'title',
        'message',
        'link',
        'dedupe_key',
        'read_at',
        'handled_at',
        'handled_by',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'handled_at' => 'datetime',
    ];

    public function handler(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}

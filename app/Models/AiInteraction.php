<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiInteraction extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'user_id',
        'feature',
        'prompt_hash',
        'tokens_in',
        'tokens_out',
        'model',
        'ok',
        'created_at',
    ];

    protected $casts = [
        'ok' => 'boolean',
        'created_at' => 'datetime',
    ];
    public $timestamps = false;

}

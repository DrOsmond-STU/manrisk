<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FrameworkItem extends Model
{
    use HasFactory, BelongsToOrganization, Auditable;

    protected $fillable = [
        'clause',
        'group',
        'title',
        'status',
        'score',
        'module',
        'note',
    ];
}

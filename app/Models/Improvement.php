<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Improvement extends Model
{
    use HasFactory, BelongsToOrganization, Auditable, SoftDeletes;

    protected $fillable = [
        'code',
        'source_type',
        'source_ref',
        'title',
        'description',
        'pic_id',
        'unit_id',
        'due_date',
        'status',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'unit_id');
    }
}

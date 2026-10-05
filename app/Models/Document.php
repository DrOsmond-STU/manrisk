<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory, BelongsToOrganization, Auditable, SoftDeletes;

    protected $fillable = [
        'subject_type',
        'subject_id',
        'type',
        'title',
        'version',
        'path',
        'original_name',
        'mime',
        'size',
        'hash',
        'uploaded_by',
        'expires_at',
        'status',
        'replaces_id',
    ];

    protected $casts = [
        'expires_at' => 'date',
    ];
    protected $auditExclude = ['path', 'hash'];


    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}

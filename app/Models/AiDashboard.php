<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dashboard AI Analitik tersimpan (spesifikasi widget; data dihitung ulang saat dibuka). */
class AiDashboard extends Model
{
    use BelongsToOrganization, Auditable;

    protected $fillable = ['user_id', 'title', 'prompt', 'widgets', 'shared'];

    protected $casts = ['widgets' => 'array', 'shared' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditLabel(): string
    {
        return $this->title;
    }
}

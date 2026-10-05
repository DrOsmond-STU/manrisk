<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Jejak audit: hanya ditambah, tidak pernah diubah atau dihapus dari aplikasi. */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['organization_id', 'user_id', 'action', 'subject_type', 'subject_id', 'subject_label', 'changes', 'context', 'ip', 'user_agent', 'created_at'];

    protected $casts = ['changes' => 'array', 'created_at' => 'datetime'];

    public static function record(string $action, Model $subject, array $changes = [], ?string $context = null): self
    {
        $user = auth()->user();
        $req = request();
        return static::create([
            'organization_id' => $subject->organization_id ?? $user?->organization_id,
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'subject_label' => method_exists($subject, 'auditLabel') ? mb_substr($subject->auditLabel(), 0, 160) : null,
            'changes' => $changes ?: null,
            'context' => $context ?? ($req?->route()?->getName()),
            'ip' => $req?->ip(),
            'user_agent' => mb_substr((string) $req?->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

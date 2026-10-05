<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;

/**
 * Mencatat pembuatan, perubahan (field lama → baru), dan penghapusan ke audit_logs.
 * Field pada $auditExclude tidak dicatat.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($m) => AuditLog::record('created', $m, $m->auditSnapshot()));
        static::updated(function ($m) {
            $changes = [];
            foreach ($m->getChanges() as $field => $new) {
                if (in_array($field, $m->auditExcluded(), true)) {
                    continue;
                }
                $changes[$field] = [$m->getOriginal($field), $new];
            }
            if ($changes) {
                AuditLog::record('updated', $m, $changes);
            }
        });
        static::deleted(fn ($m) => AuditLog::record('deleted', $m, []));
        if (method_exists(static::class, 'restored')) {
            static::restored(fn ($m) => AuditLog::record('restored', $m, []));
        }
    }

    public function auditExcluded(): array
    {
        return array_merge(['updated_at', 'created_at', 'remember_token', 'password', 'deleted_at'], $this->auditExclude ?? []);
    }

    public function auditSnapshot(): array
    {
        $out = [];
        foreach ($this->getAttributes() as $k => $v) {
            if (!in_array($k, $this->auditExcluded(), true)) {
                $out[$k] = [null, $v];
            }
        }
        return $out;
    }

    public function auditLabel(): string
    {
        return (string) ($this->code ?? $this->name ?? $this->title ?? $this->getKey());
    }
}

<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy extends BasePolicy
{
    protected array $writers = [];

    protected array $deleters = [];

    protected ?string $unitColumn = null;

    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin', 'risk_admin', 'risk_manager', 'auditor');
    }
}

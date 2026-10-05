<?php

namespace App\Policies;

use App\Models\Scope;
use App\Models\User;

class ScopePolicy extends BasePolicy
{
    protected array $writers = ['super_admin', 'risk_admin', 'risk_manager'];

    protected array $deleters = ['super_admin', 'risk_admin'];

    protected ?string $unitColumn = null;

}

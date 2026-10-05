<?php

namespace App\Policies;

use App\Models\Objective;
use App\Models\User;

class ObjectivePolicy extends BasePolicy
{
    protected array $writers = ['super_admin', 'risk_admin', 'risk_manager'];

    protected array $deleters = ['super_admin', 'risk_admin'];

    protected ?string $unitColumn = null;

}

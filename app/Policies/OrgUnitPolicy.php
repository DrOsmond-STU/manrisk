<?php

namespace App\Policies;

use App\Models\OrgUnit;
use App\Models\User;

class OrgUnitPolicy extends BasePolicy
{
    protected array $writers = ['super_admin', 'risk_admin'];

    protected array $deleters = ['super_admin', 'risk_admin'];

    protected ?string $unitColumn = null;

}

<?php

namespace App\Policies;

use App\Models\RiskCategory;
use App\Models\User;

class RiskCategoryPolicy extends BasePolicy
{
    protected array $writers = ['super_admin', 'risk_admin', 'risk_manager'];

    protected array $deleters = ['super_admin', 'risk_admin'];

    protected ?string $unitColumn = null;

}

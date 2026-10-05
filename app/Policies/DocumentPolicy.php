<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy extends BasePolicy
{
    protected array $deleters = ['super_admin', 'risk_admin', 'risk_manager', 'risk_officer', 'risk_owner'];

    protected ?string $unitColumn = null;

}

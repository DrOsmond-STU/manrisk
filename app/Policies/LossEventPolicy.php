<?php

namespace App\Policies;

use App\Models\LossEvent;
use App\Models\User;

class LossEventPolicy extends BasePolicy
{
    protected ?string $unitColumn = null;

}

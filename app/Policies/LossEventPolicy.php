<?php

namespace App\Policies;

use App\Models\LossEvent;
use App\Models\User;

class LossEventPolicy extends BasePolicy
{
    /** Loss database bersifat organisasi: hanya pengelola risiko pusat yang menulis. */
    protected array $writers = ['super_admin', 'risk_admin', 'risk_manager'];

    protected ?string $unitColumn = null;

}

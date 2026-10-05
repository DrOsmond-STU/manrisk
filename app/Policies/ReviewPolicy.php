<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy extends BasePolicy
{
    protected ?string $unitColumn = null;

}

<?php

namespace App\Policies;

use App\Models\ActionPlan;
use App\Models\User;

class ActionPlanPolicy extends BasePolicy
{
    public function progress(User $user, ActionPlan $plan): bool
    {
        return $this->update($user, $plan) || ($plan->pic_id === $user->id && !$user->isReadOnly());
    }
}

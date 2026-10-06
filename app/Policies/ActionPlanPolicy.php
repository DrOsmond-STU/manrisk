<?php

namespace App\Policies;

use App\Models\ActionPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActionPlanPolicy extends BasePolicy
{
    /** PIC dari unit lain tetap boleh membuka action plan yang ditugaskan kepadanya. */
    public function view(User $user, Model $plan): bool
    {
        return parent::view($user, $plan) || ($plan->pic_id === $user->id && $plan->organization_id === $user->organization_id);
    }

    public function progress(User $user, ActionPlan $plan): bool
    {
        return $this->update($user, $plan) || ($plan->pic_id === $user->id && !$user->isReadOnly());
    }
}

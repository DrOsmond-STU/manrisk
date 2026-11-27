<?php

namespace App\Policies;

use App\Models\Risk;
use App\Models\User;

class RiskPolicy extends BasePolicy
{
    public function changeScore(User $user, Risk $risk): bool
    {
        return $user->hasRole('super_admin', 'risk_admin', 'risk_manager', 'risk_officer') && $this->inScope($user, $risk);
    }

    protected array $stateful = ['submit', 'close'];

    public function submit(User $user, Risk $risk): bool
    {
        return $this->update($user, $risk) && in_array($risk->status, ['draft'], true);
    }

    public function close(User $user, Risk $risk): bool
    {
        return $this->update($user, $risk) && $risk->status !== 'closed';
    }
}

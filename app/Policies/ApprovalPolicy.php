<?php

namespace App\Policies;

use App\Models\Approval;
use App\Models\User;

class ApprovalPolicy extends BasePolicy
{
    protected array $writers = ['super_admin', 'risk_admin', 'risk_manager', 'risk_officer', 'risk_owner'];

    protected array $deleters = ['super_admin'];

    protected ?string $unitColumn = null;

    public function decide(User $user, Approval $approval): bool
    {
        if ($approval->status !== 'pending' || $approval->requester_id === $user->id) {
            return false;
        }
        $step = $approval->steps->firstWhere('step_no', $approval->current_step);
        return $step && ($user->role === $step->role || $user->role === 'super_admin');
    }
}

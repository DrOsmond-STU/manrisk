<?php

namespace App\Policies;

use App\Models\Approval;
use App\Models\User;

class ApprovalPolicy extends BasePolicy
{
    protected array $writers = ['super_admin', 'risk_admin', 'risk_manager', 'risk_officer', 'risk_owner'];

    protected array $deleters = ['super_admin'];

    protected ?string $unitColumn = null;

    public function view(User $user, \Illuminate\Database\Eloquent\Model $model): bool
    {
        return $model->organization_id === $user->organization_id && \App\Support\UnitScope::canSee($user, $model->subject);
    }

    public function decide(User $user, Approval $approval): bool
    {
        if ($approval->status !== 'pending' || $approval->requester_id === $user->id) {
            return false;
        }
        $step = $approval->steps->firstWhere('step_no', $approval->current_step);
        if (!$step || ($user->role !== $step->role && $user->role !== 'super_admin')) {
            return false;
        }
        // Penyetuju bercakupan unit hanya dapat memutus pengajuan atas objek di unitnya
        return \App\Support\UnitScope::canSee($user, $approval->subject);
    }
}

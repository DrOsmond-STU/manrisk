<?php

namespace App\Policies;

use App\Models\Kri;
use App\Models\User;

class KriPolicy extends BasePolicy
{
    protected array $writers = ['super_admin', 'risk_admin', 'risk_manager', 'risk_officer'];

    protected ?string $unitColumn = null;

    protected function inScope(User $user, \Illuminate\Database\Eloquent\Model $model): bool
    {
        if (!$user->isUnitScoped()) {
            return parent::inScope($user, $model);
        }
        return $model->risk ? $user->canAccessUnit($model->risk->unit_id) : true;
    }
}

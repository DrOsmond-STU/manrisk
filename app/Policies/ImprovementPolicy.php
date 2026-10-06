<?php

namespace App\Policies;

use App\Models\Improvement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ImprovementPolicy extends BasePolicy
{
    /** PIC improvement (mis. dari unit lain) tetap boleh melihat & memperbarui tindakannya. */
    protected function inScope(User $user, Model $model): bool
    {
        return parent::inScope($user, $model) || ($model->pic_id !== null && $model->pic_id === $user->id && $model->organization_id === $user->organization_id);
    }
}

<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy extends BasePolicy
{
    protected array $writers = ['super_admin'];

    protected array $deleters = ['super_admin'];

    protected ?string $unitColumn = null;

    public function viewAny(User $user): bool
    {
        return $user->role === 'super_admin';
    }

    public function view(User $user, \Illuminate\Database\Eloquent\Model $model): bool
    {
        return $user->role === 'super_admin' || $user->id === $model->id;
    }
}

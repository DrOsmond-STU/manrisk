<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Support\UnitScope;
use Illuminate\Database\Eloquent\Model;

/** Dokumen mewarisi cakupan dari objek yang dilampiri; hapus hanya oleh pengunggah atau admin. */
class DocumentPolicy extends BasePolicy
{
    protected ?string $unitColumn = null;

    protected function inScope(User $user, Model $model): bool
    {
        if ($model->organization_id !== $user->organization_id) {
            return false;
        }
        return UnitScope::canSee($user, $model->subject);
    }

    public function update(User $user, Model $model): bool
    {
        return $user->hasRole(...$this->writers) && $this->inScope($user, $model)
            && ($model->uploaded_by === $user->id || $user->hasRole('risk_admin', 'risk_manager'));
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->update($user, $model);
    }
}

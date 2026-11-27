<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Dasar semua kebijakan: peran menentukan kemampuan, cakupan unit membatasi data
 * untuk Risk Officer/Risk Owner (spesifikasi §3). Peran baca-saja tidak pernah menulis.
 */
abstract class BasePolicy
{
    /** Peran yang boleh membuat/mengubah objek modul ini. */
    protected array $writers = ['super_admin', 'risk_admin', 'risk_manager', 'risk_officer', 'risk_owner'];

    /** Peran yang boleh menghapus. */
    protected array $deleters = ['super_admin', 'risk_admin', 'risk_manager'];

    /** Kolom unit pada model untuk pemeriksaan cakupan (null = tidak dibatasi unit). */
    protected ?string $unitColumn = 'unit_id';

    /** Kemampuan yang bergantung pada status objek: Super Admin tetap tunduk pada aturan statusnya. */
    protected array $stateful = [];

    public function before(User $user, string $ability): ?bool
    {
        if ($user->role === 'super_admin' && !in_array($ability, $this->stateful, true)) {
            return true;
        }
        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Model $model): bool
    {
        return $this->inScope($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(...$this->writers);
    }

    public function update(User $user, Model $model): bool
    {
        return $user->hasRole(...$this->writers) && $this->inScope($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->hasRole(...$this->deleters) && $this->inScope($user, $model);
    }

    protected function inScope(User $user, Model $model): bool
    {
        if (isset($model->organization_id) && $model->organization_id !== $user->organization_id) {
            return false;
        }
        if ($this->unitColumn === null || !$user->isUnitScoped()) {
            return true;
        }
        $unitId = $model->{$this->unitColumn} ?? null;
        if ($unitId === null && method_exists($model, 'risk') && $model->risk) {
            $unitId = $model->risk->unit_id;
        }
        if ($unitId === null) {
            // objek tanpa unit (mis. kontrol lintas unit): boleh bila tidak terkait risiko di luar cakupan
            if (method_exists($model, 'risks')) {
                $ids = $user->accessibleUnitIds();
                return !$model->risks()->whereNotIn('unit_id', $ids)->exists();
            }
            return true;
        }
        return $user->canAccessUnit((int) $unitId);
    }
}

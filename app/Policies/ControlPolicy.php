<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ControlPolicy extends BasePolicy
{
    /**
     * Melihat kontrol (pengguna bercakupan unit): unitnya dalam cakupan, mengendalikan risiko dalam cakupan,
     * atau tanpa unit dan tidak terkait risiko di luar cakupan. Sama dengan daftar kontrol, sehingga kontrol
     * yang tampil di daftar dan di detail risiko selalu dapat dibuka. Mengubah/menghapus tetap mengikuti unit kontrol.
     */
    public function view(User $user, Model $model): bool
    {
        if (parent::view($user, $model)) {
            return true;
        }
        return $model->organization_id === $user->organization_id && $user->isUnitScoped()
            && $model->risks()->whereIn('unit_id', $user->accessibleUnitIds())->exists();
    }
}

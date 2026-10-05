<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Membatasi query pada organisasi pengguna yang sedang masuk (multi-tenant).
 * Penjaga rekursi diperlukan karena memuat pengguna dari sesi juga menjalankan scope ini.
 */
class OrganizationScope implements Scope
{
    private static bool $resolving = false;

    public function apply(Builder $builder, Model $model): void
    {
        if (self::$resolving) {
            return;
        }
        self::$resolving = true;
        try {
            $user = auth()->user();
        } finally {
            self::$resolving = false;
        }
        if ($user) {
            $builder->where($model->getTable() . '.organization_id', $user->organization_id);
        }
    }
}

<?php

namespace App\Support;

use App\Models\Risk;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Pembatasan data untuk peran bercakupan unit (Risk Officer/Risk Owner).
 * Dipakai pada daftar yang menampilkan objek lintas modul (peringatan, dokumen, review,
 * persetujuan, laporan) agar tidak membocorkan data unit lain.
 */
class UnitScope
{
    /** null = tanpa batas; selain itu daftar id unit yang boleh diakses. */
    public static function units(?User $user): ?array
    {
        return $user?->accessibleUnitIds();
    }

    /** Subquery id risiko yang boleh diakses. */
    public static function riskIdsQuery(User $user)
    {
        return Risk::query()->select('id')->whereIn('unit_id', $user->accessibleUnitIds() ?? []);
    }

    /** Batasi query polimorfik (subject_type/subject_id) ke subjek dalam cakupan. */
    public static function morph(Builder $q, User $user, bool $allowUnattached = true, string $prefix = ''): Builder
    {
        $units = $user->accessibleUnitIds();
        if ($units === null) {
            return $q;
        }
        $risks = fn () => self::riskIdsQuery($user);
        return $q->where(function ($w) use ($units, $risks, $allowUnattached, $prefix) {
            $w->where(fn ($x) => $x->where($prefix . 'subject_type', 'risk')->whereIn($prefix . 'subject_id', $risks()))
              ->orWhere(fn ($x) => $x->where($prefix . 'subject_type', 'incident')->whereIn($prefix . 'subject_id', \App\Models\Incident::query()->select('id')->whereIn('unit_id', $units)))
              ->orWhere(fn ($x) => $x->where($prefix . 'subject_type', 'action_plan')->whereIn($prefix . 'subject_id', \App\Models\ActionPlan::query()->select('id')->whereIn('unit_id', $units)))
              ->orWhere(fn ($x) => $x->where($prefix . 'subject_type', 'kri')->whereIn($prefix . 'subject_id', \App\Models\Kri::query()->select('id')->whereIn('risk_id', $risks())))
              ->orWhere(fn ($x) => $x->where($prefix . 'subject_type', 'control')->whereIn($prefix . 'subject_id', \App\Models\Control::query()->select('id')->where(fn ($c) => $c->whereNull('unit_id')->orWhereIn('unit_id', $units))))
              ->orWhere(fn ($x) => $x->where($prefix . 'subject_type', 'approval')->whereIn($prefix . 'subject_id', \App\Models\Approval::query()->select('id')->where('subject_type', 'risk')->whereIn('subject_id', $risks())));
            if ($allowUnattached) {
                $w->orWhereNull($prefix . 'subject_type');
            }
        });
    }

    /** Apakah pengguna boleh melihat subjek polimorfik ini. */
    public static function canSee(User $user, ?Model $subject): bool
    {
        if ($subject === null) {
            return true;
        }
        return $user->can('view', $subject);
    }
}

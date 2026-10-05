<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\OrgUnit;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Satu akun per peran. Kata sandi awal dari MR_SEED_PASSWORD (.env); wajib diganti saat login pertama di produksi. */
class UserSeeder extends Seeder
{
    public const ACCOUNTS = [
        ['Super Admin', 'admin@manrisk.id', 'super_admin', 'Administrator Sistem'],
        ['Risk Administrator', 'riskadmin@manrisk.id', 'risk_admin', 'Admin Manajemen Risiko'],
        ['Risk Manager', 'manager@manrisk.id', 'risk_manager', 'Kepala Unit Manajemen Risiko'],
        ['Risk Officer', 'officer@manrisk.id', 'risk_officer', 'Risk Officer Direktorat TI'],
        ['Risk Owner', 'owner@manrisk.id', 'risk_owner', 'Direktur Teknologi Informasi'],
        ['Management', 'management@manrisk.id', 'management', 'Kepala Badan'],
        ['Auditor', 'auditor@manrisk.id', 'auditor', 'Auditor Internal'],
    ];

    public function run(): void
    {
        $org = Organization::firstOrFail();
        $password = env('MR_SEED_PASSWORD', 'ManRisk#2026');
        $mustChange = (bool) env('MR_SEED_MUST_CHANGE', app()->isProduction());
        $tiUnit = OrgUnit::withoutGlobalScopes()->where('organization_id', $org->id)->where('name', 'Direktorat Teknologi Informasi')->first();
        foreach (self::ACCOUNTS as [$name, $email, $role, $position]) {
            User::withoutGlobalScopes()->updateOrCreate(['organization_id' => $org->id, 'email' => $email], [
                'name' => $name, 'password' => $password, 'role' => $role, 'position' => $position, 'active' => true, 'must_change_password' => $mustChange,
                'unit_id' => in_array($role, ['risk_officer', 'risk_owner'], true) ? $tiUnit?->id : null, 'password_changed_at' => now(),
            ]);
        }
    }
}

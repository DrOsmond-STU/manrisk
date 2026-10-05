<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Profil pengguna (preferensi) dan pengaturan organisasi (Super Admin/Risk Admin). */
class SettingsController extends Controller
{
    public function profile(Request $request)
    {
        $u = $request->user();
        return Inertia::render('Settings/Profile', ['user' => $u->only('name', 'email', 'position', 'role', 'preferences', 'last_login_at', 'last_login_ip', 'password_changed_at') + ['role_label' => $u->roleLabel(), 'unit' => $u->unit?->name],
            'sessions' => \App\Support\SessionManager::count($u),
            'logins' => \App\Models\AuthLog::where('user_id', $u->id)->whereIn('event', ['login_success', 'login_failed', 'logout'])->latest('id')->limit(10)->get(['event', 'ip', 'user_agent', 'created_at'])]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'position' => ['nullable', 'string', 'max:120'], 'preferences' => ['nullable', 'array:notify,theme'], 'preferences.notify' => ['nullable', 'array', 'max:2'], 'preferences.notify.*' => [Rule::in(['database', 'mail'])], 'preferences.theme' => ['nullable', Rule::in(['light', 'dark'])]]);
        $request->user()->update($data);
        return $this->ok('Profil disimpan.');
    }

    public function organization(Request $request)
    {
        abort_unless($request->user()->hasRole('super_admin', 'risk_admin'), 403);
        $org = $request->user()->organization;
        return Inertia::render('Settings/Organization', ['organization' => $org->only('id', 'name', 'code', 'settings'), 'policy' => [
            'password_min' => config('manrisk.password_min'), 'login_max_attempts' => config('manrisk.login_max_attempts'), 'login_lock_minutes' => config('manrisk.login_lock_minutes'),
            'session_idle_minutes' => config('manrisk.session_idle_minutes'), 'session_absolute_hours' => config('manrisk.session_absolute_hours'), 'approval_sla_days' => config('manrisk.approval_sla_days'), 'upload_max_kb' => config('manrisk.upload_max_kb'), 'ai' => ['enabled' => config('manrisk.ai.enabled'), 'has_key' => (bool) config('manrisk.ai.api_key'), 'model' => config('manrisk.ai.model')],
        ]]);
    }

    public function updateOrganization(Request $request)
    {
        abort_unless($request->user()->hasRole('super_admin'), 403);
        $org = $request->user()->organization;
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_-]+$/i', Rule::unique('organizations')->ignore($org->id)], 'settings' => ['nullable', 'array:review_cycle,fiscal_year_start,currency,appetite_statement,appetite_basis,appetite_date'], 'settings.appetite_statement' => ['nullable', 'string', 'max:4000'], 'settings.appetite_basis' => ['nullable', 'string', 'max:500'], 'settings.appetite_date' => ['nullable', 'date'], 'settings.review_cycle' => ['nullable', Rule::in(['monthly', 'quarterly', 'semester'])], 'settings.fiscal_year_start' => ['nullable', 'integer', 'between:1,12'], 'settings.currency' => ['nullable', 'string', 'max:5']]);
        $org->update($data);
        return $this->ok('Pengaturan organisasi disimpan.');
    }
}

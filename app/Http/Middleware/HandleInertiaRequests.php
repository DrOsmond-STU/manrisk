<?php

namespace App\Http\Middleware;

use App\Models\Alert;
use App\Models\Approval;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        return array_merge(parent::share($request), [
            'app' => ['name' => config('manrisk.app_name'), 'env' => app()->environment()],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'role_label' => $user->roleLabel(),
                    'unit_id' => $user->unit_id,
                    'unit' => $user->unit?->name,
                    'position' => $user->position,
                    'read_only' => $user->isReadOnly(),
                    'unit_scoped' => $user->isUnitScoped(),
                    'must_change_password' => (bool) $user->must_change_password,
                    'organization' => $user->organization?->only('id', 'name', 'code'),
                ] : null,
            ],
            'badges' => fn () => $user ? [
                'alerts' => \App\Support\UnitScope::morph(Alert::query(), $user)->whereNull('read_at')->whereNull('handled_at')->count(),
                'approvals' => \App\Support\UnitScope::morph(Approval::query(), $user, false)->where('status', 'pending')->with('steps')->get()
                    ->filter(fn ($a) => $a->requester_id !== $user->id && ($s = $a->steps->firstWhere('step_no', $a->current_step)) && ($s->role === $user->role || $user->role === 'super_admin'))->count(),
            ] : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'temp_password' => fn () => $request->session()->get('temp_password'),
            ],
            'labels' => fn () => [
                'roles' => \App\Models\User::ROLES,
                'levels' => \App\Support\Scoring::LEVELS,
                'evaluations' => \App\Support\Scoring::EVALUATIONS,
                'treatments' => config('manrisk.treatments'),
                'risk_statuses' => config('manrisk.risk_statuses'),
                'incident_statuses' => config('manrisk.incident_statuses'),
                'source_kinds' => config('manrisk.source_kinds'),
                'document_types' => config('manrisk.document_types'),
                'effectiveness' => config('manrisk.effectiveness'),
                'priorities' => config('manrisk.priorities'),
                'unit_types' => config('manrisk.unit_types'),
                'approval_types' => \App\Services\ApprovalService::TYPES,
            ],
        ]);
    }
}

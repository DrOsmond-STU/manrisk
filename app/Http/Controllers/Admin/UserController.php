<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuthLog;
use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Administrasi pengguna (Super Admin): CRUD, reset sandi, aktif/nonaktif, cakupan unit. */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);
        $f = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'role' => ['nullable', Rule::in(array_keys(User::ROLES))], 'active' => ['nullable', 'boolean']]);
        $q = User::with('unit:id,name');
        if (!empty($f['q'])) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$f['q']}%")->orWhere('email', 'like', "%{$f['q']}%"));
        }
        if (!empty($f['role'])) {
            $q->where('role', $f['role']);
        }
        if (isset($f['active']) && $f['active'] !== '') {
            $q->where('active', (bool) $f['active']);
        }
        $mfaRoles = (array) ($request->user()->organization->settings['mfa_required_roles'] ?? []);
        return Inertia::render('Admin/Users', [
            'users' => $q->orderBy('name')->paginate(25)->withQueryString()->through(fn ($u) => $u->only('id', 'name', 'email', 'role', 'position', 'unit_id', 'scope_units', 'active', 'must_change_password', 'mfa_enabled', 'last_login_at', 'last_login_ip', 'created_at') + ['role_label' => $u->roleLabel(), 'unit' => $u->unit?->name, 'mfa_required' => in_array($u->role, $mfaRoles, true)]),
            'filters' => $f,
            'units' => $this->unitOptions(),
            'stats' => ['total' => User::count(), 'active' => User::where('active', true)->count(), 'by_role' => User::selectRaw('role, count(*) n')->groupBy('role')->pluck('n', 'role')],
            'auth_logs' => AuthLog::with('user:id,name')->where('organization_id', $request->user()->organization_id)->latest('id')->limit(30)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', User::class);
        $data = $this->rules($request);
        $temp = $data['password'] ?? $this->temporaryPassword();
        $user = User::create(collect($data)->except('password')->all() + ['password' => $temp, 'must_change_password' => true, 'active' => $data['active'] ?? true]);
        AuthLog::write('user_created', $user->email, $user, 'by=' . $request->user()->email);
        return back()->with('success', "Pengguna {$user->name} dibuat.")->with('temp_password', ['email' => $user->email, 'password' => $temp]);
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);
        $data = $this->rules($request, $user);
        if ($user->id === $request->user()->id && (($data['role'] ?? $user->role) !== 'super_admin' || isset($data['active']) && !$data['active'])) {
            return back()->with('error', 'Anda tidak dapat menurunkan peran atau menonaktifkan akun sendiri.');
        }
        if ($user->role === 'super_admin' && (($data['role'] ?? 'super_admin') !== 'super_admin' || (isset($data['active']) && !$data['active'])) && $this->isLastSuperAdmin($user)) {
            return back()->with('error', 'Harus ada minimal satu Super Admin aktif.');
        }
        $wasActive = $user->active;
        $roleChanged = ($data['role'] ?? $user->role) !== $user->role;
        $user->update(collect($data)->except('password')->all());
        if ($user->wasChanged('email')) {
            \App\Support\Mfa::forgetDevices($user);
            AuthLog::write('email_changed', $user->email, $user, 'by=' . $request->user()->email);
        }
        if (($wasActive && !$user->active) || $roleChanged) {
            \App\Support\SessionManager::revokeAll($user); // hak akses berubah → sesi lama diputus
        }
        return $this->ok('Pengguna diperbarui.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $this->authorize('update', $user);
        $temp = $this->temporaryPassword();
        PasswordPolicy::apply($user, $temp, true);
        \App\Support\SessionManager::revokeAll($user);
        \App\Support\Mfa::forgetDevices($user);
        AuthLog::write('password_reset', $user->email, $user, 'by=' . $request->user()->email);
        return back()->with('success', "Kata sandi {$user->name} direset.")->with('temp_password', ['email' => $user->email, 'password' => $temp]);
    }

    /** Reset MFA pengguna: matikan MFA pribadi, hapus kode pemulihan & perangkat tepercaya. */
    public function resetMfa(Request $request, User $user)
    {
        $this->authorize('update', $user);
        \App\Support\Mfa::reset($user);
        \App\Support\SessionManager::revokeAll($user);
        AuthLog::write('mfa_reset', $user->email, $user, 'by=' . $request->user()->email);
        $note = \App\Support\Mfa::requiredByPolicy($user) ? ' MFA tetap diminta saat login karena diwajibkan kebijakan untuk perannya; pastikan alamat emailnya benar.' : '';
        return back()->with('success', "MFA {$user->name} direset; kode pemulihan dan perangkat tepercaya dicabut." . $note);
    }

    public function toggle(Request $request, User $user)
    {
        $this->authorize('update', $user);
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Tidak dapat menonaktifkan akun sendiri.');
        }
        if ($user->active && $user->role === 'super_admin' && $this->isLastSuperAdmin($user)) {
            return back()->with('error', 'Harus ada minimal satu Super Admin aktif.');
        }
        $user->update(['active' => !$user->active]);
        if (!$user->active) {
            \App\Support\SessionManager::revokeAll($user);
        }
        AuthLog::write($user->active ? 'user_enabled' : 'user_disabled', $user->email, $user, 'by=' . $request->user()->email);
        return $this->ok($user->active ? 'Akun diaktifkan.' : 'Akun dinonaktifkan; sesi aktifnya akan diputus.');
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorize('delete', $user);
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Tidak dapat menghapus akun sendiri.');
        }
        // Spesifikasi F-ADM-01: hanya akun yang belum pernah masuk & tanpa keterkaitan data yang boleh dihapus
        if ($user->last_login_at !== null) {
            return back()->with('error', 'Akun yang pernah masuk tidak dapat dihapus demi jejak audit; nonaktifkan saja.');
        }
        if (\App\Models\Risk::where('owner_id', $user->id)->exists()) {
            return back()->with('error', 'Pengguna masih menjadi pemilik risiko; alihkan dahulu atau nonaktifkan akun.');
        }
        if ($user->role === 'super_admin' && $this->isLastSuperAdmin($user)) {
            return back()->with('error', 'Harus ada minimal satu Super Admin aktif.');
        }
        $email = $user->email;
        $user->forceFill(['active' => false, 'email' => mb_substr("deleted-{$user->id}-" . $email, 0, 160)])->saveQuietly();
        \App\Support\SessionManager::revokeAll($user);
        $user->delete();
        AuthLog::write('user_deleted', $email, $user, 'by=' . $request->user()->email);
        return $this->ok('Pengguna dihapus.');
    }

    private function rules(Request $request, ?User $user = null): array
    {
        $request->merge(['email' => \Illuminate\Support\Str::lower(trim((string) $request->input('email')))]);
        $org = $request->user()->organization_id;
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:160', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'position' => ['nullable', 'string', 'max:120'],
            'unit_id' => ['nullable', Rule::exists('org_units', 'id')->where('organization_id', $org)],
            'scope_units' => ['nullable', 'array', 'max:50'],
            'scope_units.*' => ['integer', Rule::exists('org_units', 'id')->where('organization_id', $org)],
            'active' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', ...PasswordPolicy::rules()],
        ]);
    }

    private function isLastSuperAdmin(User $user): bool
    {
        return !User::where('role', 'super_admin')->where('active', true)->where('id', '!=', $user->id)->exists();
    }

    private function temporaryPassword(): string
    {
        return Str::password(14, letters: true, numbers: true, symbols: true, spaces: false);
    }
}

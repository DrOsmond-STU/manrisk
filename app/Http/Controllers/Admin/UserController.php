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
        return Inertia::render('Admin/Users', [
            'users' => $q->orderBy('name')->paginate(25)->withQueryString()->through(fn ($u) => $u->only('id', 'name', 'email', 'role', 'position', 'unit_id', 'scope_units', 'active', 'must_change_password', 'last_login_at', 'last_login_ip', 'created_at') + ['role_label' => $u->roleLabel(), 'unit' => $u->unit?->name]),
            'filters' => $f,
            'units' => $this->unitOptions(),
            'stats' => ['total' => User::count(), 'active' => User::where('active', true)->count(), 'by_role' => User::selectRaw('role, count(*) n')->groupBy('role')->pluck('n', 'role')],
            'auth_logs' => AuthLog::with('user:id,name')->latest('id')->limit(30)->get(),
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
        $user->update(collect($data)->except('password')->all());
        return $this->ok('Pengguna diperbarui.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $this->authorize('update', $user);
        $temp = $this->temporaryPassword();
        PasswordPolicy::apply($user, $temp, true);
        AuthLog::write('password_reset', $user->email, $user, 'by=' . $request->user()->email);
        return back()->with('success', "Kata sandi {$user->name} direset.")->with('temp_password', ['email' => $user->email, 'password' => $temp]);
    }

    public function toggle(Request $request, User $user)
    {
        $this->authorize('update', $user);
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Tidak dapat menonaktifkan akun sendiri.');
        }
        $user->update(['active' => !$user->active]);
        AuthLog::write($user->active ? 'user_enabled' : 'user_disabled', $user->email, $user, 'by=' . $request->user()->email);
        return $this->ok($user->active ? 'Akun diaktifkan.' : 'Akun dinonaktifkan; sesi aktifnya akan diputus.');
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorize('delete', $user);
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Tidak dapat menghapus akun sendiri.');
        }
        if (\App\Models\Risk::where('owner_id', $user->id)->exists()) {
            return back()->with('error', 'Pengguna masih menjadi pemilik risiko; alihkan dahulu atau nonaktifkan akun.');
        }
        $user->update(['active' => false]);
        $user->delete();
        AuthLog::write('user_deleted', $user->email, $user, 'by=' . $request->user()->email);
        return $this->ok('Pengguna dihapus.');
    }

    private function rules(Request $request, ?User $user = null): array
    {
        $org = $request->user()->organization_id;
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users')->where('organization_id', $org)->ignore($user?->id)->whereNull('deleted_at')],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'position' => ['nullable', 'string', 'max:120'],
            'unit_id' => ['nullable', Rule::exists('org_units', 'id')->where('organization_id', $org)],
            'scope_units' => ['nullable', 'array', 'max:50'],
            'scope_units.*' => ['integer', Rule::exists('org_units', 'id')->where('organization_id', $org)],
            'active' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', ...PasswordPolicy::rules()],
        ]);
    }

    private function temporaryPassword(): string
    {
        return Str::password(14, letters: true, numbers: true, symbols: true, spaces: false);
    }
}

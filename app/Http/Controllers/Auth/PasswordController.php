<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuthLog;
use App\Support\PasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('Auth/ChangePassword', [
            'forced' => (bool) $request->user()->must_change_password,
            'min' => config('manrisk.password_min'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', ...PasswordPolicy::rules()],
        ]);
        if (!Hash::check($data['current_password'], $user->password)) {
            AuthLog::write('password_change_failed', $user->email, $user, 'wrong_current');
            throw ValidationException::withMessages(['current_password' => 'Kata sandi saat ini salah.']);
        }
        if ($data['current_password'] === $data['password']) {
            throw ValidationException::withMessages(['password' => 'Kata sandi baru harus berbeda dari kata sandi saat ini.']);
        }
        if (PasswordPolicy::wasUsedBefore($user, $data['password'])) {
            throw ValidationException::withMessages(['password' => 'Kata sandi ini pernah dipakai. Gunakan kata sandi lain.']);
        }
        PasswordPolicy::apply($user, $data['password']);
        AuthLog::write('password_changed', $user->email, $user);
        $request->session()->regenerate();
        \App\Support\SessionManager::revokeAll($user, $request->session()->getId());
        return redirect()->route('dashboard')->with('success', 'Kata sandi berhasil diganti.');
    }
}

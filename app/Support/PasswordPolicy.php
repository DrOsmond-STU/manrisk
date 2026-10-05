<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/** Kebijakan kata sandi (spesifikasi §16.1): panjang minimum, kompleksitas, riwayat 5 terakhir. */
class PasswordPolicy
{
    public static function rules(): array
    {
        return [Password::min((int) config('manrisk.password_min'))->mixedCase()->numbers()->symbols(), 'max:128'];
    }

    public static function wasUsedBefore(User $user, string $plain): bool
    {
        if (Hash::check($plain, $user->password)) {
            return true;
        }
        $history = DB::table('password_history')->where('user_id', $user->id)->orderByDesc('id')->limit((int) config('manrisk.password_history'))->pluck('password');
        foreach ($history as $hash) {
            if (Hash::check($plain, $hash)) {
                return true;
            }
        }
        return false;
    }

    public static function apply(User $user, string $plain, bool $mustChange = false): void
    {
        DB::table('password_history')->insert(['user_id' => $user->id, 'password' => $user->password, 'created_at' => now()]);
        $keep = (int) config('manrisk.password_history');
        $old = DB::table('password_history')->where('user_id', $user->id)->orderByDesc('id')->skip($keep)->limit(100)->pluck('id');
        if ($old->isNotEmpty()) {
            DB::table('password_history')->whereIn('id', $old)->delete();
        }
        $user->forceFill(['password' => $plain, 'must_change_password' => $mustChange, 'password_changed_at' => now()])->save();
    }
}

<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Pemutusan sesi berbasis basis data (driver session = database). */
class SessionManager
{
    /** Hapus semua sesi milik pengguna, kecuali sesi saat ini bila diberikan. */
    public static function revokeAll(User $user, ?string $exceptSessionId = null): int
    {
        if (config('session.driver') !== 'database') {
            return 0;
        }
        $q = DB::table(config('session.table', 'sessions'))->where('user_id', $user->id);
        if ($exceptSessionId) {
            $q->where('id', '!=', $exceptSessionId);
        }
        return $q->delete();
    }

    public static function count(User $user): int
    {
        if (config('session.driver') !== 'database') {
            return 1;
        }
        return DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->count();
    }
}

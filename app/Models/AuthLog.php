<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Log autentikasi: login sukses/gagal, logout, kunci akun, ganti sandi, sesi berakhir. */
class AuthLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['organization_id', 'user_id', 'email', 'event', 'ip', 'user_agent', 'detail', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public static function write(string $event, ?string $email = null, ?User $user = null, ?string $detail = null): self
    {
        $req = request();
        return static::create([
            'organization_id' => $user?->organization_id,
            'user_id' => $user?->id,
            'email' => $email ? mb_substr($email, 0, 160) : null,
            'event' => $event,
            'ip' => $req?->ip(),
            'user_agent' => mb_substr((string) $req?->userAgent(), 0, 255),
            'detail' => $detail ? mb_substr($detail, 0, 255) : null,
            'created_at' => now(),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

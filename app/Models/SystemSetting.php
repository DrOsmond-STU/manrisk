<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Pengaturan tingkat sistem (lintas organisasi), mis. konfigurasi SMTP. Nilai disimpan sebagai JSON. */
class SystemSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = null;

    protected $fillable = ['key', 'value', 'updated_by'];

    protected $casts = ['value' => 'array'];

    public static function read(string $key): array
    {
        return static::find($key)?->value ?? [];
    }

    public static function write(string $key, array $value, ?int $by = null): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $by]);
    }
}

<?php

namespace App\Support;

use App\Models\AuthLog;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Penyimpanan berkas yang aman (spesifikasi §16.3, F-DOC-05): tipe diperiksa dari isi (magic bytes),
 * pemindaian antivirus opsional, nama acak di luar docroot, hash SHA-256.
 */
class DocumentStore
{
    public static function rules(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'file', 'max:' . config('manrisk.upload_max_kb'),
            'mimetypes:' . implode(',', config('manrisk.upload_mimes')), 'extensions:' . implode(',', config('manrisk.upload_extensions'))];
    }

    public static function store(UploadedFile $file, User $user, ?Model $subject, array $meta, string $field = 'file'): Document
    {
        $detected = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        $allowed = array_merge(config('manrisk.upload_mimes'), ['application/zip', 'application/x-ole-storage', 'application/CDFV2']);
        if (!in_array($detected, $allowed, true)) {
            throw ValidationException::withMessages([$field => 'Isi berkas tidak sesuai dengan tipe yang diizinkan (' . $detected . ').']);
        }
        if (($scan = self::virusScan($file->getRealPath())) !== true) {
            AuthLog::write('upload_blocked', $user->email, $user, mb_substr((string) $scan, 0, 200));
            throw ValidationException::withMessages([$field => 'Berkas ditolak oleh pemindai antivirus.']);
        }
        $ext = strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs('documents/' . $user->organization_id . '/' . now()->format('Y/m'), Str::uuid() . '.' . $ext, 'local');
        return Document::create($meta + [
            'subject_type' => $subject?->getMorphClass(), 'subject_id' => $subject?->getKey(), 'path' => $path,
            'original_name' => mb_substr(preg_replace('/[^\w .()\-]/u', '_', $file->getClientOriginalName()), 0, 255),
            'mime' => $detected, 'size' => $file->getSize(), 'hash' => hash_file('sha256', $file->getRealPath()), 'uploaded_by' => $user->id,
        ]);
    }

    /** true bila bersih atau pemindai tidak dikonfigurasi; string pesan bila terdeteksi/galat. */
    public static function virusScan(string $path)
    {
        $bin = config('manrisk.clamav_path');
        if (!$bin || !is_executable($bin)) {
            return true;
        }
        $out = [];
        exec(escapeshellcmd($bin) . ' --no-summary ' . escapeshellarg($path) . ' 2>&1', $out, $code);
        return $code === 0 ? true : ('clamav exit ' . $code . ': ' . implode(' ', $out));
    }
}

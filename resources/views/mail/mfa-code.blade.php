@extends('mail.layout', ['title' => 'Kode verifikasi'])
@section('body')
<p>Halo {{ $name }},</p>
<p>Gunakan kode berikut untuk {{ $action }}:</p>
<p style="margin:18px 0;text-align:center"><span style="display:inline-block;padding:12px 22px;border-radius:10px;background:#e8f1fb;border:1px solid #b9d3f0;font-family:Consolas,Menlo,monospace;font-size:30px;letter-spacing:8px;font-weight:700;color:#0b4f9c">{{ $code }}</span></p>
<p>Kode berlaku <b>{{ $ttl }} menit</b> dan hanya dapat dipakai sekali. Permintaan berasal dari alamat IP <b>{{ $ip ?: '-' }}</b>.</p>
<p style="color:#9a3412">Jangan bagikan kode ini kepada siapa pun, termasuk yang mengaku administrator. Jika Anda tidak sedang masuk, segera ganti kata sandi dan hubungi administrator ManRisk.</p>
@endsection

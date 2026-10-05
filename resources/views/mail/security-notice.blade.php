@extends('mail.layout', ['title' => $title])
@section('body')
<p>Halo {{ $name }},</p>
<p><b>{{ $title }}</b></p>
<p>{{ $body }}</p>
<p style="color:#526883">Alamat IP: {{ $ip ?: '-' }}</p>
<p style="color:#9a3412">Jika ini bukan tindakan Anda, segera ganti kata sandi dan hubungi administrator ManRisk.</p>
@endsection

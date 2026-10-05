@extends('reports.layout')
@section('content')
<table><thead><tr><th>Kode</th><th>Kontrol</th><th>Tipe</th><th>Frekuensi</th><th>Pemilik</th><th>Desain</th><th>Operasi</th><th>Uji terakhir</th><th>Risiko</th></tr></thead><tbody>
@foreach($controls as $c)<tr><td>{{ $c->code }}</td><td>{{ $c->name }}</td><td>{{ $c->type }}</td><td>{{ $c->frequency }}</td><td>{{ $c->owner?->name }}</td><td>{{ config('manrisk.effectiveness')[$c->design_eff] ?? '—' }}</td><td>{{ config('manrisk.effectiveness')[$c->operating_eff] ?? '—' }}</td><td>{{ $c->last_tested_at?->format('d/m/Y') }}</td><td>{{ $c->risks->pluck('code')->implode(', ') }}</td></tr>@endforeach
</tbody></table>
@endsection

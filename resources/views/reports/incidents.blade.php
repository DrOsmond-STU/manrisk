@extends('reports.layout')
@section('content')
<table><thead><tr><th>Kode</th><th>Waktu</th><th>Insiden</th><th>Risiko</th><th>Unit</th><th>Penyebab</th><th>Tindakan korektif</th><th class="num">Kerugian (Rp)</th><th>Status</th></tr></thead><tbody>
@foreach($incidents as $i)<tr><td>{{ $i->code }}</td><td>{{ $i->occurred_at->format('d/m/Y H:i') }}</td><td><b>{{ $i->title }}</b></td><td>{{ $i->risk?->code }}</td><td>{{ $i->unit?->name }}</td><td>{{ $i->cause }}</td><td>{{ $i->corrective_action }}</td><td class="num">{{ number_format($i->loss_amount, 0, ',', '.') }}</td><td>{{ config('manrisk.incident_statuses')[$i->status] ?? '' }}</td></tr>@endforeach
</tbody></table>
<p class="meta">Total kerugian: Rp {{ number_format($incidents->sum('loss_amount'), 0, ',', '.') }}</p>
@endsection

@extends('reports.layout')
@section('content')
<table><thead><tr><th>Kode</th><th>Risiko</th><th>Unit</th><th class="num">Inheren</th><th class="num">Residual</th><th class="num">Proyeksi</th><th class="num">Target</th><th>Level residual</th><th>Status</th></tr></thead><tbody>
@foreach($risks as $r)
<tr><td>{{ $r->code }}</td><td>{{ $r->name }}</td><td>{{ $r->unit?->name }}</td><td class="num">{{ $r->inherent_score }} ({{ $r->inherent_l }}×{{ $r->inherent_i }})</td><td class="num"><b>{{ $r->residual_score }}</b> ({{ $r->residual_l }}×{{ $r->residual_i }})</td>
<td class="num">{{ $r->projected['score'] }}</td><td class="num">{{ $r->target_score }} ({{ $r->target_l }}×{{ $r->target_i }})</td><td><span class="lv {{ $r->residual_level }}">{{ \App\Support\Scoring::levelLabel($r->residual_level) }}</span></td><td>{{ config('manrisk.risk_statuses')[$r->status] ?? '' }}</td></tr>
@endforeach
</tbody></table>
@endsection

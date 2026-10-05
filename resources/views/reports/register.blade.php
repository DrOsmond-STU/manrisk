@extends('reports.layout')
@section('content')
<table><thead><tr><th>Kode</th><th>Risiko</th><th>Unit / Pemilik</th><th>Kategori</th><th>Pernyataan (penyebab → peristiwa → dampak)</th><th class="num">Inh</th><th class="num">Res</th><th>Level</th><th>Evaluasi</th><th class="num">Tgt</th><th>Treatment</th><th>Status</th></tr></thead><tbody>
@foreach($risks as $r)
<tr><td>{{ $r->code }}</td><td><b>{{ $r->name }}</b></td><td>{{ $r->unit?->name }}<br>{{ $r->owner?->name }}</td><td>{{ $r->category?->name }}</td><td>{{ $r->statement() }}</td><td class="num">{{ $r->inherent_score }}</td><td class="num"><b>{{ $r->residual_score }}</b></td><td><span class="lv {{ $r->residual_level }}">{{ \App\Support\Scoring::levelLabel($r->residual_level) }}</span></td><td>{{ \App\Support\Scoring::EVALUATIONS[$r->evaluation] ?? $r->evaluation }}</td><td class="num">{{ $r->target_score }}</td><td>{{ config('manrisk.treatments')[$r->treatment] ?? $r->treatment }}</td><td>{{ config('manrisk.risk_statuses')[$r->status] ?? $r->status }}</td></tr>
@endforeach
</tbody></table>
<p class="meta">{{ $risks->count() }} risiko.</p>
@endsection

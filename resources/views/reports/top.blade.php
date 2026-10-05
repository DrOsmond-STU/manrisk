@extends('reports.layout')
@section('content')
<table><thead><tr><th>#</th><th>Kode</th><th>Risiko</th><th>Unit</th><th class="num">Inh</th><th class="num">Res</th><th>Level</th><th>Evaluasi</th><th>Perlakuan & action plan</th><th>Tren</th></tr></thead><tbody>
@foreach($risks as $i => $r)
<tr><td>{{ $i + 1 }}</td><td>{{ $r->code }}</td><td><b>{{ $r->name }}</b><br><span class="meta">{{ $r->statement() }}</span></td><td>{{ $r->unit?->name }}</td><td class="num">{{ $r->inherent_score }}</td><td class="num"><b>{{ $r->residual_score }}</b></td>
<td><span class="lv {{ $r->residual_level }}">{{ \App\Support\Scoring::levelLabel($r->residual_level) }}</span></td><td>{{ \App\Support\Scoring::EVALUATIONS[$r->evaluation] ?? '' }}</td>
<td>{{ config('manrisk.treatments')[$r->treatment] ?? '' }}@foreach($r->actionPlans as $p)<br>• {{ $p->title }} ({{ $p->progress }}%)@endforeach</td><td>{{ ['up' => 'Naik', 'down' => 'Turun', 'flat' => 'Tetap'][$r->trend] ?? '' }}</td></tr>
@endforeach
</tbody></table>
@endsection

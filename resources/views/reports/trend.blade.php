@extends('reports.layout')
@section('content')
<p>Perbandingan skor residual periode <b>{{ $period_from }}</b> dan <b>{{ $period_to }}</b>: naik <b>{{ $up }}</b>, turun <b>{{ $down }}</b>, tetap <b>{{ $flat }}</b>.</p>
@php($lvls = array_reverse(\App\Support\Scoring::levels(), true))
<table class="kpis"><tr><td>Level</td>@foreach($lvls as $lb)<td>{{ $lb }}</td>@endforeach</tr>
<tr><td>{{ $period_from }}</td>@foreach($lvls as $k => $lb)<td><b>{{ $dist_from[$k] ?? 0 }}</b></td>@endforeach</tr>
<tr><td>{{ $period_to }}</td>@foreach($lvls as $k => $lb)<td><b>{{ $dist_to[$k] ?? 0 }}</b></td>@endforeach</tr></table>
<table><thead><tr><th>Kode</th><th>Risiko</th><th>Unit</th><th class="num">{{ $period_from }}</th><th class="num">{{ $period_to }}</th><th class="num">Perubahan</th></tr></thead><tbody>
@foreach($rows->sortByDesc('delta') as $x)
<tr><td>{{ $x['code'] }}</td><td>{{ $x['name'] }}</td><td>{{ $x['unit'] }}</td><td class="num">{{ $x['from'] ?? '—' }}</td><td class="num">{{ $x['to'] }}</td><td class="num">{{ $x['delta'] === null ? '—' : ($x['delta'] > 0 ? '+' . $x['delta'] : $x['delta']) }}</td></tr>
@endforeach
</tbody></table>
@endsection

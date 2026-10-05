@extends('reports.layout')
@section('content')
<p>Perbandingan skor residual periode <b>{{ $period_from }}</b> dan <b>{{ $period_to }}</b>: naik <b>{{ $up }}</b>, turun <b>{{ $down }}</b>, tetap <b>{{ $flat }}</b>.</p>
<table class="kpis"><tr><td>Level</td><td>Sangat Tinggi</td><td>Tinggi</td><td>Sedang</td><td>Rendah</td></tr>
<tr><td>{{ $period_from }}</td><td><b>{{ $dist_from['very_high'] }}</b></td><td><b>{{ $dist_from['high'] }}</b></td><td><b>{{ $dist_from['medium'] }}</b></td><td><b>{{ $dist_from['low'] }}</b></td></tr>
<tr><td>{{ $period_to }}</td><td><b>{{ $dist_to['very_high'] }}</b></td><td><b>{{ $dist_to['high'] }}</b></td><td><b>{{ $dist_to['medium'] }}</b></td><td><b>{{ $dist_to['low'] }}</b></td></tr></table>
<table><thead><tr><th>Kode</th><th>Risiko</th><th>Unit</th><th class="num">{{ $period_from }}</th><th class="num">{{ $period_to }}</th><th class="num">Perubahan</th></tr></thead><tbody>
@foreach($rows->sortByDesc('delta') as $x)
<tr><td>{{ $x['code'] }}</td><td>{{ $x['name'] }}</td><td>{{ $x['unit'] }}</td><td class="num">{{ $x['from'] ?? '—' }}</td><td class="num">{{ $x['to'] }}</td><td class="num">{{ $x['delta'] === null ? '—' : ($x['delta'] > 0 ? '+' . $x['delta'] : $x['delta']) }}</td></tr>
@endforeach
</tbody></table>
@endsection

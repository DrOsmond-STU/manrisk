@extends('reports.layout')
@section('content')
<table><thead><tr><th>Kode</th><th>KRI</th><th>Risiko</th><th>Pemilik</th><th class="num">Waspada</th><th class="num">Kritis</th><th class="num">Nilai</th><th>Status</th></tr></thead><tbody>
@foreach($kris as $k)<tr><td>{{ $k->code }}</td><td>{{ $k->name }} ({{ $k->unit }})</td><td>{{ $k->risk?->code }}</td><td>{{ $k->owner?->name }}</td><td class="num">{{ $k->threshold_warn }}</td><td class="num">{{ $k->threshold_crit }}</td><td class="num"><b>{{ $k->last_value }}</b></td><td>{{ ['normal' => 'Normal', 'warning' => 'Waspada', 'critical' => 'Kritis'][$k->status] }}</td></tr>@endforeach
</tbody></table>
@endsection

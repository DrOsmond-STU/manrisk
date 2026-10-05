@extends('reports.layout')
@section('content')
@foreach([['Residual', $cells], ['Inheren', $inherent_cells]] as [$label, $set])
<h2>Matriks {{ $label }}</h2>
<table style="width:auto"><tr><td></td>@for($i = 1; $i <= 5; $i++)<th style="text-align:center">Dampak {{ $i }}</th>@endfor</tr>
@for($l = 5; $l >= 1; $l--)
<tr><th>Kemungkinan {{ $l }}</th>@for($i = 1; $i <= 5; $i++)<td class="lv {{ $matrix["$l-$i"] ?? 'medium' }}" style="text-align:center;width:70px;height:34px;font-size:13px;border-radius:0"><b>{{ count($set["$l-$i"] ?? []) }}</b></td>@endfor</tr>
@endfor
</table>
@endforeach
<h2>Daftar risiko per sel (residual)</h2>
<table><thead><tr><th>Sel (L×I)</th><th class="num">Skor</th><th>Risiko</th></tr></thead><tbody>
@foreach(collect($cells)->sortKeysDesc() as $k => $rs)
<tr><td>{{ str_replace('-', ' × ', $k) }}</td><td class="num">{{ array_product(explode('-', $k)) }}</td><td>@foreach($rs as $r){{ $r->code }} {{ $r->name }}<br>@endforeach</td></tr>
@endforeach
</tbody></table>
@endsection

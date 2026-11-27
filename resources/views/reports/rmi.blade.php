@extends('reports.layout')
@section('content')
@php
  $types = ['internal' => 'Penilaian internal (tahunan)', 'independent' => 'Penilaian independen'];
  $phase = $result['phase'] ? ($phases[$result['phase']]['label'] ?? $result['phase']) : '—';
@endphp
<table>
  <tr><th style="width:24%">Jenis penilaian</th><td>{{ $types[$a->type] ?? $a->type }}</td><th style="width:18%">Status</th><td>{{ $a->status === 'final' ? 'Final · ' . $a->finalized_at?->format('d/m/Y') : 'Draf' }}</td></tr>
  <tr><th>Penilai</th><td>{{ $a->assessor ?: ($a->creator?->name ?? '—') }}</td><th>Tanggal penilaian</th><td>{{ $a->assessed_at?->translatedFormat('d F Y') ?? '—' }}</td></tr>
</table>
<table class="kpis"><tr><td>Indeks RMI<b>{{ $result['index'] !== null ? number_format($result['index'], 2, ',', '.') : '—' }}</b></td><td>Fase kematangan<b style="font-size:12px">{{ $phase }}</b></td>@foreach($dimensions as $k => $label)<td>{{ $label }}<b>{{ $result['dimensions'][$k] !== null ? number_format($result['dimensions'][$k], 2, ',', '.') : '—' }}</b></td>@endforeach</tr></table>
<p>Parameter dinilai: {{ $result['scored'] }} dari {{ $result['total'] }}. Indeks = rata-rata tertimbang skor parameter (skala 1–5).</p>

<h2>Batas fase kematangan</h2>
<table><tr>@foreach($phases as $k => $p)<td style="text-align:center{{ $result['phase'] === $k ? ';background:#e3ebf6;font-weight:bold' : '' }}">{{ $p['label'] }}<br>≤ {{ number_format($p['max'], 1, ',', '.') }}</td>@endforeach</tr></table>

@foreach($dimensions as $k => $label)
@php $rows = $a->scores->filter(fn ($s) => $s->parameter?->dimension === $k)->sortBy(fn ($s) => sprintf('%05d-%s', $s->parameter->sort, $s->parameter->code)); @endphp
@if($rows->count())
<h2>{{ $label }} · skor {{ $result['dimensions'][$k] !== null ? number_format($result['dimensions'][$k], 2, ',', '.') : '—' }}</h2>
<table><thead><tr><th style="width:9%">Kode</th><th>Parameter</th><th class="num" style="width:7%">Bobot</th><th class="num" style="width:7%">Skor</th><th style="width:36%">Bukti pendukung</th></tr></thead><tbody>
@foreach($rows as $s)<tr><td>{{ $s->parameter->code }}</td><td>{{ $s->parameter->name }}</td><td class="num">{{ $s->parameter->weight }}</td><td class="num"><b>{{ $s->score ?? '—' }}</b></td><td>{{ $s->evidence ?: '—' }}</td></tr>@endforeach
</tbody></table>
@endif
@endforeach

@if($a->note)
<h2>Catatan dan rekomendasi peningkatan</h2>
<p style="white-space:pre-line">{{ $a->note }}</p>
@endif
<p style="color:#526883;font-size:8.5px">Penilaian Risk Maturity Index sesuai Peraturan Menteri BUMN Nomor PER-2/MBU/03/2023: penilaian internal dilakukan setiap tahun dan penilaian independen paling sedikit sekali dalam tiga tahun. Parameter dan batas fase mengikuti petunjuk teknis RMI yang berlaku.</p>
@endsection

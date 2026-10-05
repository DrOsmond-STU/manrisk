@extends('reports.layout')
@section('content')
<h2>1. Ringkasan profil risiko</h2>
<table class="kpis"><tr><td>Total risiko aktif<b>{{ $summary['total'] }}</b></td><td>Sangat tinggi<b>{{ $summary['very_high'] }}</b></td><td>Tinggi<b>{{ $summary['high'] }}</b></td><td>Sedang<b>{{ $summary['medium'] }}</b></td><td>Rendah<b>{{ $summary['low'] }}</b></td><td>Rata-rata residual<b>{{ $summary['avg'] }}</b></td></tr></table>
<table class="kpis"><tr><td>Action plan selesai<b>{{ $plans['done'] ?? 0 }}</b></td><td>Berjalan<b>{{ $plans['running'] ?? 0 }}</b></td><td>Terlambat<b>{{ $plans['overdue'] ?? 0 }}</b></td><td>KRI kritis<b>{{ $kris['critical'] ?? 0 }}</b></td><td>KRI waspada<b>{{ $kris['warning'] ?? 0 }}</b></td><td>Insiden YTD<b>{{ $incidents_ytd }}</b></td><td>Kerugian YTD<b>Rp {{ number_format($loss_ytd, 0, ',', '.') }}</b></td></tr></table>
<h2>2. Risk appetite per kategori</h2>
<table><thead><tr><th>Kategori</th><th class="num">Risiko</th><th class="num">Skor maks</th><th class="num">Appetite</th><th class="num">Tolerance</th><th>Status</th></tr></thead><tbody>
@foreach($categories as $c)<tr><td>{{ $c['name'] }}</td><td class="num">{{ $c['n'] }}</td><td class="num">{{ $c['max'] }}</td><td class="num">{{ $c['appetite'] }}</td><td class="num">{{ $c['tolerance'] }}</td><td>{{ $c['max'] > $c['tolerance'] ? 'Melewati tolerance' : ($c['max'] > $c['appetite'] ? 'Melewati appetite' : 'Dalam appetite') }}</td></tr>@endforeach
</tbody></table>
<h2>3. Risiko utama</h2>
<table><thead><tr><th>Kode</th><th>Risiko</th><th>Unit</th><th class="num">Residual</th><th>Level</th><th>Evaluasi</th><th>Treatment</th><th>Tren</th></tr></thead><tbody>
@foreach($risks->take(15) as $r)<tr><td>{{ $r->code }}</td><td><b>{{ $r->name }}</b></td><td>{{ $r->unit?->name }}</td><td class="num">{{ $r->residual_score }}</td><td><span class="lv {{ $r->residual_level }}">{{ \App\Support\Scoring::levelLabel($r->residual_level) }}</span></td><td>{{ \App\Support\Scoring::EVALUATIONS[$r->evaluation] ?? '' }}</td><td>{{ config('manrisk.treatments')[$r->treatment] ?? '' }}</td><td>{{ ['up' => 'Naik', 'down' => 'Turun', 'flat' => 'Tetap'][$r->trend] ?? '' }}</td></tr>@endforeach
</tbody></table>
@if(!empty($ai_label))<p class="meta"><i>Rekomendasi disusun dengan bantuan aturan/AI dan telah ditinjau pembuat laporan.</i></p>@endif
<h2>4. Rekomendasi</h2>
<ul>
<li>Prioritaskan penyelesaian action plan untuk {{ $summary['very_high'] + $summary['high'] }} risiko berlevel tinggi/sangat tinggi.</li>
<li>Tinjau kategori yang melewati tolerance dan putuskan opsi treatment tambahan atau penerimaan risiko secara formal.</li>
<li>Tindak lanjuti KRI yang kritis dan pastikan kontrol kunci diuji sesuai jadwal.</li>
</ul>
@endsection

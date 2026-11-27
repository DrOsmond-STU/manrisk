@extends('reports.layout')
@section('content')
@php
  $rp = fn ($v) => $v === null ? '—' : 'Rp ' . number_format((float) $v, 0, ',', '.');
  $lvls = array_reverse($level_labels, true);
  $planLabels = ['done' => 'Selesai', 'running' => 'Berjalan', 'overdue' => 'Terlambat', 'verify' => 'Menunggu verifikasi', 'todo' => 'Belum mulai', 'cancelled' => 'Dibatalkan'];
@endphp
<div class="meta">Periode: {{ $period_start->translatedFormat('d F Y') }} – {{ $period_end->translatedFormat('d F Y') }}@if($intensity) · Klasifikasi intensitas risiko: <b>{{ $intensity }}</b>@endif @if(!empty($settings['parent_entity'])) · BUMN induk: {{ $settings['parent_entity'] }}@endif</div>

<h2>1. Strategi risiko dan selera risiko</h2>
<table>
  <tr><th style="width:24%">Strategi risiko</th><td>{{ $settings['risk_strategy'] ?? 'Belum ditetapkan.' }}</td></tr>
  <tr><th>Pernyataan risk appetite</th><td>{{ $settings['appetite_statement'] ?? 'Belum ditetapkan.' }}@if(!empty($settings['appetite_date'])) <i>(ditetapkan {{ \Carbon\Carbon::parse($settings['appetite_date'])->translatedFormat('d F Y') }})</i>@endif</td></tr>
  <tr><th>Kapasitas risiko</th><td>{{ $rp($exposure['capacity']) }}</td></tr>
  <tr><th>Risk limit total eksposur residual</th><td>{{ $rp($exposure['limit']) }}@if($exposure['pct'] !== null) · terpakai {{ $exposure['pct'] }}%@endif</td></tr>
</table>

<h2>2. Profil risiko</h2>
<table class="kpis"><tr><td>Risiko aktif<b>{{ $profile->count() }}</b></td>@foreach($lvls as $k => $lb)<td>{{ $lb }}<b>{{ $summary[$k] ?? 0 }}</b><span style="font-size:8px">inheren {{ $inherent_summary[$k] ?? 0 }}</span></td>@endforeach</tr></table>
<table class="kpis"><tr><td>Eksposur inheren<b style="font-size:12px">{{ $rp($exposure['inherent']) }}</b></td><td>Eksposur residual<b style="font-size:12px">{{ $rp($exposure['exposure']) }}</b></td><td>Target eksposur<b style="font-size:12px">{{ $rp($exposure['target']) }}</b></td><td>Risiko terkuantifikasi<b>{{ $exposure['quantified'] }}/{{ $exposure['total'] }}</b></td><td>Target triwulan tercapai<b>{{ $targets_met }}/{{ $targets_set }}</b></td></tr></table>

<h2>3. Peta risiko inheren dan residual</h2>
<table style="width:100%;border:0"><tr>
@foreach([['Inheren', $inherent_cells], ['Residual', $cells]] as [$label, $set])
<td style="border:0;width:50%">
  <b>{{ $label }}</b>
  <table style="width:auto;margin-top:4px"><tr><td style="border:0"></td>@for($i = 1; $i <= 5; $i++)<th style="text-align:center">I{{ $i }}</th>@endfor</tr>
  @for($l = 5; $l >= 1; $l--)<tr><th>L{{ $l }}</th>@for($i = 1; $i <= 5; $i++)<td class="lv {{ $matrix["$l-$i"] ?? 'medium' }}" style="text-align:center;width:46px;height:22px;border-radius:0"><b>{{ count($set["$l-$i"] ?? []) }}</b></td>@endfor</tr>@endfor
  </table>
</td>
@endforeach
</tr></table>

<h2>4. Realisasi risiko residual terhadap target triwulan {{ $quarter }}</h2>
<table><thead><tr><th>Kode</th><th>Risiko</th><th>Taksonomi</th><th>Pemilik</th><th class="num">Inheren</th><th class="num">Eksposur inheren</th><th class="num">Target TW{{ $quarter }}</th><th class="num">Target eksposur</th><th class="num">Realisasi</th><th class="num">Realisasi eksposur</th><th>Level</th><th>Capaian</th></tr></thead><tbody>
@forelse($profile as $p)
<tr><td>{{ $p['code'] }}</td><td>{{ $p['name'] }}</td><td>{{ $p['taxonomy'] }}@if($p['bumn_code'])<br><span style="color:#526883">{{ $p['bumn_code'] }}</span>@endif</td><td>{{ $p['owner'] }}</td><td class="num">{{ $p['inherent'] }}</td><td class="num">{{ $rp($p['inherent_exposure']) }}</td><td class="num">{{ $p['target'] ?? '—' }}</td><td class="num">{{ $rp($p['target_exposure']) }}</td><td class="num"><b>{{ $p['real'] }}</b></td><td class="num">{{ $rp($p['real_exposure']) }}</td><td><span class="lv {{ $p['level'] }}">{{ $level_labels[$p['level']] ?? $p['level'] }}</span></td><td>{{ $p['met'] === null ? 'Target belum ditetapkan' : ($p['met'] ? 'Tercapai' : 'Belum tercapai') }}</td></tr>
@empty
<tr><td colspan="12">Tidak ada risiko aktif.</td></tr>
@endforelse
</tbody></table>

<h2>5. Pemakaian risk limit per taksonomi</h2>
@if($limits->count())
<table><thead><tr><th>Taksonomi</th><th>Kode BUMN</th><th class="num">Risiko</th><th class="num">Eksposur residual</th><th class="num">Risk limit</th><th class="num">Pemakaian</th><th>Status</th></tr></thead><tbody>
@foreach($limits as $c)<tr><td style="padding-left:{{ 5 + ($c['depth'] - 1) * 12 }}px">{{ $c['name'] }}</td><td>{{ $c['bumn_code'] ?? '—' }}</td><td class="num">{{ $c['n'] }}</td><td class="num">{{ $rp($c['exposure']) }}</td><td class="num">{{ $rp($c['limit']) }}</td><td class="num">{{ $c['pct'] !== null ? $c['pct'] . '%' : '—' }}</td><td>{{ ['ok' => 'Dalam limit', 'near' => 'Mendekati limit', 'breach' => 'Melampaui limit', 'none' => 'Limit belum ditetapkan'][$c['status']] }}</td></tr>@endforeach
</tbody></table>
@else
<p>Eksposur risiko dan risk limit belum diisi.</p>
@endif

<h2>6. Realisasi perlakuan risiko dan biaya</h2>
<table class="kpis"><tr><td>Rencana perlakuan<b>{{ $plans->count() }}</b></td><td>Selesai<b>{{ $plans->where('status', 'done')->count() }}</b></td><td>Terlambat<b>{{ $plans->where('status', 'overdue')->count() }}</b></td><td>Anggaran<b style="font-size:12px">{{ $rp($plan_budget) }}</b></td><td>Realisasi biaya<b style="font-size:12px">{{ $rp($plan_cost) }}</b></td></tr></table>
<table><thead><tr><th>Kode</th><th>Risiko</th><th>Perlakuan</th><th>PIC</th><th>Tenggat</th><th class="num">Progres</th><th>Status</th><th class="num">Anggaran</th><th class="num">Realisasi biaya</th></tr></thead><tbody>
@forelse($plans as $p)<tr><td>{{ $p['code'] }}</td><td>{{ $p['risk'] }}</td><td>{{ $p['title'] }}</td><td>{{ $p['pic'] }}</td><td>{{ $p['due']?->format('d/m/Y') }}</td><td class="num">{{ $p['progress'] }}%</td><td>{{ $planLabels[$p['status']] ?? $p['status'] }}</td><td class="num">{{ $rp($p['budget']) }}</td><td class="num">{{ $rp($p['actual_cost']) }}</td></tr>
@empty<tr><td colspan="9">Tidak ada rencana perlakuan.</td></tr>@endforelse
</tbody></table>

<h2>7. Ikhtisar perubahan profil risiko</h2>
<p>Risiko baru dalam triwulan: <b>{{ $new_risks }}</b> · risiko ditutup: <b>{{ $closed_risks }}</b> · perubahan level dibanding akhir triwulan sebelumnya: naik <b>{{ $changes->where('dir', 1)->count() }}</b>, turun <b>{{ $changes->where('dir', -1)->count() }}</b>.</p>
@if($changes->whereIn('dir', [1, -1])->count())
<table><thead><tr><th>Kode</th><th>Risiko</th><th>Level awal</th><th>Level akhir</th><th>Arah</th></tr></thead><tbody>
@foreach($changes->whereIn('dir', [1, -1]) as $c)<tr><td>{{ $c['code'] }}</td><td>{{ $c['name'] }}</td><td>{{ $level_labels[$c['from']] ?? $c['from'] }}</td><td>{{ $level_labels[$c['to']] ?? $c['to'] }}</td><td>{{ $c['dir'] > 0 ? 'Naik' : 'Turun' }}</td></tr>@endforeach
</tbody></table>
@endif

<h2>8. Key Risk Indicator</h2>
<table><thead><tr><th>Kode</th><th>KRI</th><th>Risiko</th><th>Pemilik</th><th class="num">Waspada</th><th class="num">Kritis</th><th class="num">Nilai terakhir</th><th>Status</th></tr></thead><tbody>
@forelse($kris as $k)<tr><td>{{ $k->code }}</td><td>{{ $k->name }} ({{ $k->unit }})</td><td>{{ $k->risk?->code }}</td><td>{{ $k->owner?->name }}</td><td class="num">{{ $k->threshold_warn }}</td><td class="num">{{ $k->threshold_crit }}</td><td class="num"><b>{{ $k->last_value }}</b></td><td>{{ ['normal' => 'Normal', 'warning' => 'Waspada', 'critical' => 'Kritis'][$k->status] ?? $k->status }}</td></tr>
@empty<tr><td colspan="8">Belum ada KRI aktif.</td></tr>@endforelse
</tbody></table>

<h2>9. Loss event database (kejadian dalam triwulan)</h2>
<table><thead><tr><th>Kode</th><th>Tanggal</th><th>Kejadian</th><th>Risiko</th><th>Unit</th><th class="num">Kerugian</th></tr></thead><tbody>
@forelse($losses as $i)<tr><td>{{ $i->code }}</td><td>{{ $i->occurred_at?->format('d/m/Y') }}</td><td>{{ $i->title }}</td><td>{{ $i->risk?->code }}</td><td>{{ $i->unit?->name }}</td><td class="num">{{ $rp($i->loss_amount) }}</td></tr>
@empty<tr><td colspan="6">Tidak ada kerugian tercatat dalam triwulan ini.</td></tr>@endforelse
@if($losses->count())<tr><th colspan="5">Total kerugian</th><th class="num">{{ $rp($loss_total) }}</th></tr>@endif
</tbody></table>
<p style="color:#526883;font-size:8.5px">Disusun mengikuti Peraturan Menteri BUMN Nomor PER-2/MBU/03/2023 tentang Pedoman Tata Kelola dan Kegiatan Korporasi Signifikan BUMN. Sesuaikan format akhir dengan petunjuk teknis pelaporan manajemen risiko yang berlaku.</p>
@endsection

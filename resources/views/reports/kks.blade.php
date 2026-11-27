@extends('reports.layout')
@section('content')
@php
  $rp = fn ($v) => $v === null ? '—' : 'Rp ' . number_format((float) $v, 0, ',', '.');
  $treat = config('manrisk.treatments');
  $planLabels = ['done' => 'Selesai', 'running' => 'Berjalan', 'overdue' => 'Terlambat', 'verify' => 'Menunggu verifikasi', 'todo' => 'Belum mulai', 'cancelled' => 'Dibatalkan'];
  $inh = $risks->sum('inherent_exposure'); $res = $risks->sum('residual_exposure');
@endphp
<h2>1. Identitas kegiatan</h2>
<table>
  <tr><th style="width:24%">Nama kegiatan</th><td colspan="3"><b>{{ $s->name }}</b></td></tr>
  <tr><th>Jenis KKS</th><td>{{ $kks_types[$s->kks_type] ?? '—' }}</td><th style="width:18%">Nilai transaksi</th><td>{{ $rp($s->transaction_value) }}</td></tr>
  <tr><th>Unit pengusul</th><td>{{ $s->unit?->name ?? '—' }}</td><th>Periode pelaksanaan</th><td>{{ $s->period ?: '—' }}</td></tr>
  <tr><th>Organ pemutus</th><td>{{ $organs[$s->decision_organ] ?? '—' }}</td><th>Tanggal keputusan</th><td>{{ $s->decision_date?->translatedFormat('d F Y') ?? '—' }}</td></tr>
  <tr><th>Tujuan &amp; latar belakang</th><td colspan="3" style="white-space:pre-line">{{ $s->objective ?: '—' }}</td></tr>
  <tr><th>Asumsi &amp; batasan kajian</th><td colspan="3" style="white-space:pre-line">{{ $s->boundaries ?: '—' }}</td></tr>
</table>

<h2>2. Ringkasan profil risiko kegiatan</h2>
<table class="kpis"><tr><td>Risiko teridentifikasi<b>{{ $risks->count() }}</b></td>@foreach(array_reverse($level_labels, true) as $k => $lb)<td>{{ $lb }}<b>{{ $risks->where('residual_level', $k)->count() }}</b><span style="font-size:8px">residual</span></td>@endforeach</tr></table>
<table class="kpis"><tr><td>Eksposur inheren<b style="font-size:12px">{{ $rp($inh) }}</b></td><td>Eksposur residual<b style="font-size:12px">{{ $rp($res) }}</b></td><td>Melampaui toleransi risiko<b>{{ $risks->whereIn('evaluation', ['treat', 'escalate', 'critical'])->count() }}</b></td>@if($s->transaction_value)<td>Eksposur residual / nilai transaksi<b>{{ number_format($res / max($s->transaction_value, 1) * 100, 1, ',', '.') }}%</b></td>@endif</tr></table>
@if($s->area)<p><b>Dampak terhadap profil risiko korporasi:</b> <span style="white-space:pre-line">{{ $s->area }}</span></p>@endif

<h2>3. Register risiko kegiatan</h2>
@if($unit_scoped)<p style="color:#526883">Daftar dibatasi pada risiko dalam cakupan unit Anda.</p>@endif
<table><thead><tr><th>Kode</th><th>Risiko</th><th>Taksonomi</th><th>Pemilik</th><th>Inheren</th><th class="num">Eksposur inheren</th><th>Residual</th><th class="num">Eksposur residual</th><th>Perlakuan</th></tr></thead><tbody>
@forelse($risks as $r)
@php $il = $inherentLevel($r); @endphp
<tr><td>{{ $r->code }}</td><td><b>{{ $r->name }}</b><br><span style="color:#526883">Penyebab: {{ $r->cause }}<br>Dampak: {{ $r->impact }}</span></td><td>{{ $r->category?->name }}@if($r->category?->bumn_code)<br><span style="color:#526883">{{ $r->category->bumn_code }}</span>@endif</td><td>{{ $r->owner?->name }}</td><td><span class="lv {{ $il }}">{{ $level_labels[$il] ?? $il }}</span> {{ $r->inherent_score }}</td><td class="num">{{ $rp($r->inherent_exposure) }}</td><td><span class="lv {{ $r->residual_level }}">{{ $level_labels[$r->residual_level] ?? $r->residual_level }}</span> {{ $r->residual_score }}</td><td class="num">{{ $rp($r->residual_exposure) }}</td><td>{{ $treat[$r->treatment] ?? $r->treatment }}</td></tr>
@empty
<tr><td colspan="9">Belum ada risiko yang dikaitkan dengan kajian ini. Kaitkan risiko melalui kolom "Ruang lingkup / kajian KKS" pada formulir risiko.</td></tr>
@endforelse
</tbody></table>

<h2>4. Rencana perlakuan risiko</h2>
@php $plans = $risks->flatMap(fn ($r) => $r->actionPlans->map(fn ($p) => [$r, $p])); @endphp
<table><thead><tr><th>Risiko</th><th>Kode</th><th>Perlakuan</th><th>Tenggat</th><th>Status</th><th class="num">Anggaran</th></tr></thead><tbody>
@forelse($plans as [$r, $p])<tr><td>{{ $r->code }}</td><td>{{ $p->code }}</td><td>{{ $p->title }}</td><td>{{ $p->due_date?->format('d/m/Y') }}</td><td>{{ $planLabels[$p->status] ?? $p->status }}</td><td class="num">{{ $rp($p->budget) }}</td></tr>
@empty<tr><td colspan="6">Belum ada rencana perlakuan.</td></tr>@endforelse
</tbody></table>

@if($s->factors->count())
<h2>5. Konteks internal dan eksternal</h2>
<table><thead><tr><th>Faktor</th><th>Konteks</th><th>Sifat</th><th>Uraian</th></tr></thead><tbody>
@foreach($s->factors as $f)<tr><td>{{ $f->factor }}</td><td>{{ $f->kind === 'internal' ? 'Internal' : 'Eksternal' }}</td><td>{{ ['strength' => 'Kekuatan', 'weakness' => 'Kelemahan', 'opportunity' => 'Peluang', 'threat' => 'Ancaman'][$f->nature] ?? $f->nature }}</td><td>{{ $f->condition }}</td></tr>@endforeach
</tbody></table>
@endif

<h2>{{ $s->factors->count() ? '6' : '5' }}. Kesimpulan dan rekomendasi fungsi manajemen risiko</h2>
<p style="white-space:pre-line">{{ $s->conclusion ?: 'Belum diisi.' }}</p>
@if(!empty($settings['appetite_statement']))<p style="color:#526883"><b>Pernyataan risk appetite:</b> {{ $settings['appetite_statement'] }}</p>@endif

<table style="margin-top:18px"><tr><th style="width:33%;text-align:center">Disusun oleh<br>(Fungsi Manajemen Risiko)</th><th style="width:33%;text-align:center">Diperiksa oleh<br>(Unit Pengusul)</th><th style="text-align:center">Diketahui oleh<br>({{ $organs[$s->decision_organ] ?? 'Organ pemutus' }})</th></tr><tr><td style="height:60px"></td><td></td><td></td></tr></table>
<p style="color:#526883;font-size:8.5px">Kajian risiko atas Kegiatan Korporasi Signifikan sesuai Peraturan Menteri BUMN Nomor PER-2/MBU/03/2023. Sesuaikan format dengan ketentuan internal dan petunjuk teknis yang berlaku.</p>
@endsection

@extends('reports.layout')
@section('content')
<p>Pada periode <b>{{ $period }}</b> telah dilaksanakan reviu atas <b>{{ $reviews->count() }}</b> risiko dengan hasil sebagai berikut:</p>
<table><thead><tr><th>No</th><th>Risiko</th><th>Unit</th><th class="num">Skor sebelum</th><th class="num">Skor kini</th><th>Tren</th><th>Keputusan</th><th>Catatan</th><th>Reviewer</th></tr></thead><tbody>
@foreach($reviews as $i => $v)
<tr><td>{{ $i + 1 }}</td><td><b>{{ $v->risk?->code }}</b> {{ $v->risk?->name }}</td><td>{{ $v->risk?->unit?->name }}</td><td class="num">{{ $v->previous_score ?? '—' }}</td><td class="num">{{ $v->current_score }}</td>
<td>{{ ['up' => 'Naik', 'down' => 'Turun', 'flat' => 'Tetap'][$v->trend] ?? '' }}</td><td>{{ ['continue' => 'Lanjutkan', 'change_treatment' => 'Ubah perlakuan', 'close' => 'Tutup', 'escalate' => 'Eskalasi'][$v->decision] ?? $v->decision }}</td><td>{{ $v->note }}</td><td>{{ $v->reviewer?->name }}</td></tr>
@endforeach
</tbody></table>
<h2>Daftar hadir & tanda tangan elektronik</h2>
<table><thead><tr><th>Nama</th><th>Jabatan</th><th class="num">Jumlah risiko direviu</th><th>Waktu tanda tangan</th><th>Alamat IP</th></tr></thead><tbody>
@foreach($signers as $s)
<tr><td>{{ $s['name'] }}</td><td>{{ $s['position'] }}</td><td class="num">{{ $s['n'] }}</td><td>{{ \Illuminate\Support\Carbon::parse($s['at'])->format('d/m/Y H:i') }}</td><td>{{ $s['ip'] ?? '—' }}</td></tr>
@endforeach
</tbody></table>
<p class="meta">Tanda tangan elektronik sederhana: identitas pengguna yang masuk, waktu server, dan alamat IP tercatat pada sistem ManRisk dan audit trail.</p>
@endsection

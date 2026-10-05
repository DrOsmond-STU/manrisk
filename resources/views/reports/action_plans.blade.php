@extends('reports.layout')
@section('content')
<table><thead><tr><th>Kode</th><th>Risiko</th><th>Rencana</th><th>PIC</th><th>Unit</th><th>Prioritas</th><th>Tenggat</th><th class="num">Progres</th><th>Status</th></tr></thead><tbody>
@foreach($plans as $p)<tr><td>{{ $p->code }}</td><td>{{ $p->risk?->code }}</td><td>{{ $p->title }}</td><td>{{ $p->pic?->name }}</td><td>{{ $p->unit?->name }}</td><td>{{ config('manrisk.priorities')[$p->priority] ?? '' }}</td><td>{{ $p->due_date?->format('d/m/Y') }}</td><td class="num">{{ $p->progress }}%@if(isset($p->late_days)) · terlambat {{ $p->late_days }} hari @endif</td><td>{{ ['todo' => 'Belum mulai', 'running' => 'Berjalan', 'overdue' => 'Terlambat', 'done' => 'Selesai', 'cancelled' => 'Dibatalkan'][$p->status_label] ?? $p->status_label }}</td></tr>@endforeach
</tbody></table>
@endsection

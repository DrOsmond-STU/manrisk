<?php

namespace App\Exports;

use App\Support\Scoring;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(private string $type, private array $data)
    {
    }

    public function title(): string
    {
        return mb_substr($this->data['title'], 0, 30);
    }

    public function headings(): array
    {
        return match ($this->type) {
            'action_plans' => ['Kode', 'Risiko', 'Judul', 'PIC', 'Prioritas', 'Mulai', 'Tenggat', 'Progres %', 'Status', 'Anggaran'],
            'controls' => ['Kode', 'Nama', 'Tipe', 'Mode', 'Frekuensi', 'Pemilik', 'Ef. Desain', 'Ef. Operasi', 'Uji Terakhir', 'Uji Berikut', 'Risiko Terkait'],
            'kri' => ['Kode', 'Nama', 'Satuan', 'Risiko', 'Pemilik', 'Arah', 'Ambang Waspada', 'Ambang Kritis', 'Nilai Terakhir', 'Status'],
            'incidents' => ['Kode', 'Tanggal', 'Judul', 'Risiko', 'Unit', 'Status', 'Kerugian (Rp)', 'Penyebab', 'Tindakan Korektif'],
            default => ['Kode', 'Nama Risiko', 'Unit', 'Kategori', 'Pemilik', 'Penyebab', 'Peristiwa', 'Dampak', 'Sumber', 'Kontrol Ada', 'L Inh', 'I Inh', 'Skor Inh', 'L Res', 'I Res', 'Skor Res', 'Level', 'Evaluasi', 'Skor Target', 'Treatment', 'Status', 'Tren'],
        };
    }

    public function array(): array
    {
        $d = $this->data;
        return match ($this->type) {
            'action_plans' => $d['plans']->map(fn ($p) => [$p->code, $p->risk?->code, $p->title, $p->pic?->name, config('manrisk.priorities')[$p->priority] ?? $p->priority, $p->start_date?->format('Y-m-d'), $p->due_date?->format('Y-m-d'), $p->progress, $p->status_label, $p->budget])->all(),
            'controls' => $d['controls']->map(fn ($c) => [$c->code, $c->name, $c->type, $c->mode, $c->frequency, $c->owner?->name, $c->design_eff, $c->operating_eff, $c->last_tested_at?->format('Y-m-d'), $c->next_test_at?->format('Y-m-d'), $c->risks->pluck('code')->implode(', ')])->all(),
            'kri' => $d['kris']->map(fn ($k) => [$k->code, $k->name, $k->unit, $k->risk?->code, $k->owner?->name, $k->direction, $k->threshold_warn, $k->threshold_crit, $k->last_value, $k->status])->all(),
            'incidents' => $d['incidents']->map(fn ($i) => [$i->code, $i->occurred_at->format('Y-m-d H:i'), $i->title, $i->risk?->code, $i->unit?->name, config('manrisk.incident_statuses')[$i->status] ?? $i->status, $i->loss_amount, $i->cause, $i->corrective_action])->all(),
            default => $d['risks']->map(fn ($r) => [$r->code, $r->name, $r->unit?->name, $r->category?->name, $r->owner?->name, $r->cause, $r->event, $r->impact, $r->source_type . '/' . $r->source_kind, $r->existing_controls,
                $r->inherent_l, $r->inherent_i, $r->inherent_score, $r->residual_l, $r->residual_i, $r->residual_score, Scoring::levelLabel($r->residual_level), Scoring::EVALUATIONS[$r->evaluation] ?? $r->evaluation, $r->target_score,
                config('manrisk.treatments')[$r->treatment] ?? $r->treatment, config('manrisk.risk_statuses')[$r->status] ?? $r->status, $r->trend])->all(),
        };
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}

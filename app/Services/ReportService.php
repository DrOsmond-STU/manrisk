<?php

namespace App\Services;

use App\Models\ActionPlan;
use App\Models\Control;
use App\Models\Incident;
use App\Models\Kri;
use App\Models\Risk;
use App\Models\RiskCategory;
use App\Models\User;

/** Pengumpulan data laporan (§4.18); satu sumber untuk PDF & Excel. */
class ReportService
{
    public const TYPES = ['register' => 'Risk Register', 'profile' => 'Risk Profile', 'heatmap' => 'Risk Heatmap', 'top' => 'Top Risks', 'action_plans' => 'Risk Treatment (Action Plan)', 'residual' => 'Residual Risk',
        'kri' => 'KRI Report', 'incidents' => 'Risk Incident Report', 'trend' => 'Risk Trend', 'controls' => 'Control Effectiveness', 'overdue' => 'Mitigation Progress / Overdue Action', 'executive' => 'Executive Risk Report'];

    /** Format yang tersedia per jenis laporan (spesifikasi §13). */
    public const FORMATS = ['register' => ['xlsx', 'pdf'], 'profile' => ['pdf'], 'heatmap' => ['pdf'], 'top' => ['pdf', 'xlsx'], 'action_plans' => ['xlsx', 'pdf'], 'residual' => ['xlsx', 'pdf'],
        'kri' => ['pdf', 'xlsx'], 'incidents' => ['pdf', 'xlsx'], 'trend' => ['pdf', 'xlsx'], 'controls' => ['xlsx', 'pdf'], 'overdue' => ['xlsx', 'pdf'], 'executive' => ['pdf']];

    public function data(string $type, User $user, array $params = []): array
    {
        $risks = Risk::with(['unit:id,name', 'category:id,name,appetite,tolerance', 'owner:id,name'])->when($user->accessibleUnitIds(), fn ($q, $ids) => $q->whereIn('unit_id', $ids))
            ->when($params['unit_id'] ?? null, fn ($q, $u) => $q->where('unit_id', $u))->when(($params['include_closed'] ?? false) ? null : true, fn ($q) => $q->where('status', '!=', 'closed'))
            ->orderByDesc('residual_score')->get();
        $units = $user->accessibleUnitIds();
        $riskIds = $risks->pluck('id');
        $incidents = fn () => Incident::with(['risk:id,code', 'unit:id,name'])->when($units !== null, fn ($q) => $q->whereIn('unit_id', $units));
        $kris = fn () => Kri::with(['risk:id,code', 'owner:id,name'])->when($units !== null, fn ($q) => $q->whereIn('risk_id', $riskIds));
        $base = ['title' => self::TYPES[$type] ?? $type, 'org' => $user->organization?->name, 'generated_at' => now(), 'by' => $user->name, 'risks' => $risks, 'params' => $params];
        return match ($type) {
            'action_plans' => $base + ['plans' => ActionPlan::with(['risk:id,code,name', 'pic:id,name', 'unit:id,name'])->whereIn('risk_id', $riskIds)->orderBy('due_date')->get()->map(fn ($p) => $p->setAttribute('status_label', $p->computedStatus()))],
            'overdue' => $base + ['plans' => ActionPlan::with(['risk:id,code,name', 'pic:id,name', 'unit:id,name'])->whereIn('risk_id', $riskIds)->whereNull('cancelled_at')->where('progress', '<', 100)->where('due_date', '<', now()->startOfDay())->orderBy('due_date')->get()
                ->map(fn ($p) => $p->setAttribute('status_label', 'overdue')->setAttribute('late_days', (int) $p->due_date->diffInDays(now()->startOfDay())))],
            'top' => $base + ['risks' => $risks->sortBy([['residual_score', 'desc'], ['inherent_score', 'desc']])->take((int) ($params['limit'] ?? 20))->values()->load(['actionPlans' => fn ($q) => $q->whereNull('cancelled_at')])],
            'residual' => $base + ['risks' => $risks->load(['actionPlans'])->each(fn ($r) => $r->setAttribute('projected', app(\App\Support\Scoring::class)->projected($r)))],
            'heatmap' => $base + ['cells' => $risks->groupBy(fn ($r) => "{$r->residual_l}-{$r->residual_i}"), 'inherent_cells' => $risks->groupBy(fn ($r) => "{$r->inherent_l}-{$r->inherent_i}"), 'matrix' => \App\Models\CriteriaVersion::current()?->matrix ?: \App\Support\Scoring::defaultMatrix()],
            'trend' => $base + $this->trend($riskIds, $params),
            'controls' => $base + ['controls' => Control::with(['owner:id,name', 'risks' => fn ($r) => $r->select('risks.id', 'code')->when($units !== null, fn ($x) => $x->whereIn('unit_id', $units))])
                ->when($units !== null, fn ($q) => $q->where(fn ($w) => $w->whereNull('unit_id')->orWhereIn('unit_id', $units)))->orderBy('code')->get()],
            'kri' => $base + ['kris' => $kris()->orderBy('code')->get()],
            'incidents' => $base + ['incidents' => $incidents()->when($params['year'] ?? null, fn ($q, $y) => $q->whereYear('occurred_at', $y))->orderByDesc('occurred_at')->get()],
            'executive', 'profile' => $base + [
                'summary' => ['total' => $risks->count(), 'very_high' => $risks->where('residual_level', 'very_high')->count(), 'high' => $risks->where('residual_level', 'high')->count(), 'medium' => $risks->where('residual_level', 'medium')->count(), 'low' => $risks->where('residual_level', 'low')->count(), 'avg' => round((float) $risks->avg('residual_score'), 1)],
                'categories' => RiskCategory::get()->map(fn ($c) => ['name' => $c->name, 'appetite' => $c->appetite, 'tolerance' => $c->tolerance, 'n' => $risks->where('category_id', $c->id)->count(), 'max' => (int) $risks->where('category_id', $c->id)->max('residual_score')]),
                'plans' => ActionPlan::whereIn('risk_id', $riskIds)->whereNull('cancelled_at')->get()->groupBy(fn ($p) => $p->computedStatus())->map->count(),
                'kris' => $kris()->where('active', true)->get()->countBy('status'),
                'incidents_ytd' => $incidents()->whereYear('occurred_at', now()->year)->count(), 'loss_ytd' => (float) $incidents()->whereYear('occurred_at', now()->year)->sum('loss_amount'),
            ],
            default => $base,
        };
    }

    /** Perbandingan antarperiode dari snapshot (bawaan: bulan ini vs 3 bulan lalu). */
    private function trend($riskIds, array $params): array
    {
        $to = $params['period_to'] ?? now()->format('Y-m');
        $from = $params['period_from'] ?? now()->subMonths(3)->format('Y-m');
        $snap = \App\Models\RiskSnapshot::whereIn('risk_id', $riskIds)->whereIn('period', [$from, $to])->get()->groupBy('period');
        $a = $snap->get($from, collect())->keyBy('risk_id');
        $b = $snap->get($to, collect())->keyBy('risk_id');
        $rows = Risk::whereIn('id', $riskIds)->with('unit:id,name')->orderBy('code')->get()->map(fn ($r) => ['code' => $r->code, 'name' => $r->name, 'unit' => $r->unit?->name,
            'from' => $a[$r->id]->residual_score ?? null, 'to' => $b[$r->id]->residual_score ?? $r->residual_score])->map(fn ($x) => $x + ['delta' => $x['from'] === null ? null : $x['to'] - $x['from']]);
        $lv = fn ($c) => ['low' => $c->where('level', 'low')->count(), 'medium' => $c->where('level', 'medium')->count(), 'high' => $c->where('level', 'high')->count(), 'very_high' => $c->where('level', 'very_high')->count()];
        return ['period_from' => $from, 'period_to' => $to, 'rows' => $rows, 'dist_from' => $lv($snap->get($from, collect())), 'dist_to' => $lv($snap->get($to, collect())),
            'up' => $rows->where('delta', '>', 0)->count(), 'down' => $rows->where('delta', '<', 0)->count(), 'flat' => $rows->where('delta', 0)->count()];
    }

    /** Hasilkan berkas laporan: ['content' => biner, 'file' => nama, 'mime' => tipe]. */
    public function render(string $type, string $format, User $user, array $params = []): array
    {
        $report = $this->data($type, $user, $params);
        $file = 'manrisk-' . $type . '-' . now()->format('Ymd-His') . '.' . $format;
        if ($format === 'xlsx') {
            return ['content' => \Maatwebsite\Excel\Facades\Excel::raw(new \App\Exports\ReportExport($type, $report), \Maatwebsite\Excel\Excel::XLSX), 'file' => $file,
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        }
        $view = match ($type) {
            'executive', 'profile' => 'reports.executive',
            'overdue' => 'reports.action_plans',
            'action_plans', 'controls', 'kri', 'incidents', 'top', 'residual', 'trend', 'heatmap' => 'reports.' . $type,
            default => 'reports.register',
        };
        $report['ai_label'] = $type === 'executive';
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($view, $report)->setPaper('a4', in_array($type, ['register', 'residual', 'top'], true) ? 'landscape' : 'portrait');
        return ['content' => $pdf->output(), 'file' => $file, 'mime' => 'application/pdf'];
    }
}

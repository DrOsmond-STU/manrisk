<?php

namespace App\Services;

use App\Models\ActionPlan;
use App\Models\Control;
use App\Models\Incident;
use App\Models\Kri;
use App\Models\OrgUnit;
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

    /** Filter risiko yang sama dengan Risk Register (RiskController::filtered) untuk laporan. */
    public const RISK_FILTERS = ['q', 'unit_id', 'category_id', 'owner_id', 'objective_id', 'process_id', 'level', 'evaluation', 'status'];

    /** Unit efektif: cakupan pengguna ∩ unit filter (beserta sub-unitnya); null = tanpa batas. */
    public function unitIds(User $user, array $params): ?array
    {
        $units = $user->accessibleUnitIds();
        if (!empty($params['unit_id'])) {
            $f = OrgUnit::find($params['unit_id'])?->descendantIds() ?? [];
            $units = $units === null ? $f : array_values(array_intersect($units, $f));
        }
        return $units;
    }

    public function riskQuery(User $user, array $params)
    {
        $units = $this->unitIds($user, $params);
        $q = Risk::query()->when($units !== null, fn ($q) => $q->whereIn('unit_id', $units));
        if (!empty($params['q'])) {
            $term = '%' . addcslashes($params['q'], '%_\\') . '%';
            $q->where(fn ($w) => $w->where('code', 'like', $term)->orWhere('name', 'like', $term)->orWhere('event', 'like', $term)->orWhere('cause', 'like', $term)->orWhere('impact', 'like', $term));
        }
        foreach (['level' => 'residual_level', 'evaluation' => 'evaluation'] as $k => $col) {
            if (!empty($params[$k])) {
                $q->whereIn($col, explode(',', $params[$k]));
            }
        }
        foreach (['category_id', 'owner_id', 'objective_id', 'process_id'] as $k) {
            if (!empty($params[$k])) {
                $q->where($k, $params[$k]);
            }
        }
        $status = $params['status'] ?? (($params['include_closed'] ?? false) ? null : 'active');
        if ($status) {
            $status === 'active' ? $q->where('status', '!=', 'closed') : $q->where('status', $status);
        }
        return $q;
    }

    /** Ringkasan filter yang dipakai, untuk kepala laporan. */
    public function filterLabel(array $params): string
    {
        $names = fn ($model, $id) => $model::whereKey($id)->value('name');
        $map = fn ($list, $v) => collect(explode(',', $v))->map(fn ($x) => $list[$x] ?? $x)->implode(', ');
        return collect([
            'unit_id' => fn ($v) => 'Unit: ' . ($names(OrgUnit::class, $v) ?? "#{$v}") . ' (termasuk sub-unit)',
            'category_id' => fn ($v) => 'Kategori: ' . ($names(RiskCategory::class, $v) ?? "#{$v}"),
            'owner_id' => fn ($v) => 'Pemilik: ' . ($names(User::class, $v) ?? "#{$v}"),
            'objective_id' => fn ($v) => 'Sasaran: ' . ($names(\App\Models\Objective::class, $v) ?? "#{$v}"), 'process_id' => fn ($v) => 'Proses: ' . ($names(\App\Models\Process::class, $v) ?? "#{$v}"),
            'level' => fn ($v) => 'Level: ' . $map(\App\Support\Scoring::LEVELS, $v),
            'evaluation' => fn ($v) => 'Evaluasi: ' . $map(\App\Support\Scoring::EVALUATIONS, $v),
            'status' => fn ($v) => 'Status: ' . ($v === 'active' ? 'Aktif (belum ditutup)' : (config('manrisk.risk_statuses')[$v] ?? $v)),
            'q' => fn ($v) => "Kata kunci: \"{$v}\"",
        ])->filter(fn ($f, $k) => !empty($params[$k]))->map(fn ($f, $k) => $f($params[$k]))->implode(' · ');
    }

    public function data(string $type, User $user, array $params = []): array
    {
        $risks = $this->riskQuery($user, $params)->with(['unit:id,name', 'category:id,name,appetite,tolerance', 'owner:id,name'])->orderByDesc('residual_score')->get();
        $units = $this->unitIds($user, $params);
        $riskIds = $risks->pluck('id');
        // insiden & KRI mengikuti unit efektif (cakupan pengguna ∩ filter unit), bukan hanya untuk pengguna bercakupan
        $incidents = fn () => Incident::with(['risk:id,code', 'unit:id,name'])->when($units !== null, fn ($q) => $q->whereIn('unit_id', $units));
        $kris = fn () => Kri::with(['risk:id,code', 'owner:id,name'])->when($units !== null, fn ($q) => $q->whereIn('risk_id', Risk::query()->select('id')->whereIn('unit_id', $units)));
        $base = ['title' => self::TYPES[$type] ?? $type, 'filter_label' => $this->filterLabel($params), 'org' => $user->organization?->name, 'generated_at' => now(), 'by' => $user->name, 'risks' => $risks, 'params' => $params];
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

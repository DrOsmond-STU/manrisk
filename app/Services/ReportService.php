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
    public const TYPES = ['register' => 'Risk Register', 'profile' => 'Profil Risiko (Ringkasan)', 'heatmap' => 'Peta Risiko', 'action_plans' => 'Status Action Plan', 'controls' => 'Efektivitas Kontrol', 'kri' => 'KRI', 'incidents' => 'Insiden & Kerugian', 'executive' => 'Laporan Eksekutif'];

    public function data(string $type, User $user, array $params = []): array
    {
        $risks = Risk::with(['unit:id,name', 'category:id,name,appetite,tolerance', 'owner:id,name'])->when($user->accessibleUnitIds(), fn ($q, $ids) => $q->whereIn('unit_id', $ids))
            ->when($params['unit_id'] ?? null, fn ($q, $u) => $q->where('unit_id', $u))->when(($params['include_closed'] ?? false) ? null : true, fn ($q) => $q->where('status', '!=', 'closed'))
            ->orderByDesc('residual_score')->get();
        $base = ['title' => self::TYPES[$type] ?? $type, 'org' => $user->organization?->name, 'generated_at' => now(), 'by' => $user->name, 'risks' => $risks, 'params' => $params];
        return match ($type) {
            'action_plans' => $base + ['plans' => ActionPlan::with(['risk:id,code,name', 'pic:id,name'])->whereIn('risk_id', $risks->pluck('id'))->orderBy('due_date')->get()->map(fn ($p) => $p->setAttribute('status_label', $p->computedStatus()))],
            'controls' => $base + ['controls' => Control::with(['owner:id,name', 'risks:id,code'])->orderBy('code')->get()],
            'kri' => $base + ['kris' => Kri::with(['risk:id,code', 'owner:id,name'])->orderBy('code')->get()],
            'incidents' => $base + ['incidents' => Incident::with(['risk:id,code', 'unit:id,name'])->when($params['year'] ?? null, fn ($q, $y) => $q->whereYear('occurred_at', $y))->orderByDesc('occurred_at')->get()],
            'executive', 'profile' => $base + [
                'summary' => ['total' => $risks->count(), 'very_high' => $risks->where('residual_level', 'very_high')->count(), 'high' => $risks->where('residual_level', 'high')->count(), 'medium' => $risks->where('residual_level', 'medium')->count(), 'low' => $risks->where('residual_level', 'low')->count(), 'avg' => round((float) $risks->avg('residual_score'), 1)],
                'categories' => RiskCategory::get()->map(fn ($c) => ['name' => $c->name, 'appetite' => $c->appetite, 'tolerance' => $c->tolerance, 'n' => $risks->where('category_id', $c->id)->count(), 'max' => (int) $risks->where('category_id', $c->id)->max('residual_score')]),
                'plans' => ActionPlan::whereNull('cancelled_at')->get()->groupBy(fn ($p) => $p->computedStatus())->map->count(),
                'kris' => Kri::where('active', true)->get()->countBy('status'),
                'incidents_ytd' => Incident::whereYear('occurred_at', now()->year)->count(), 'loss_ytd' => (float) Incident::whereYear('occurred_at', now()->year)->sum('loss_amount'),
            ],
            default => $base,
        };
    }
}

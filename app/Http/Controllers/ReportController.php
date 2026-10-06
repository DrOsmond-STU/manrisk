<?php

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use App\Models\AuditLog;
use App\Models\ReportJob;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Reports/Index', [
            'types' => ReportService::TYPES,
            'formats' => ReportService::FORMATS,
            'units' => $this->unitOptions(),
            'categories' => \App\Models\RiskCategory::where('active', true)->orderBy('sort')->get(['id', 'name']),
            'owners' => $this->userOptions(),
            // filter awal dari query string, mis. tautan "Laporan dari filter ini" di Risk Register
            'initial' => (object) collect(\Illuminate\Support\Facades\Validator::make($request->query(), ['type' => ['nullable', Rule::in(array_keys(ReportService::TYPES))]] + $this->filterRules())->valid())
                ->only(['type', ...ReportService::RISK_FILTERS])->filter(fn ($v) => is_scalar($v) && $v !== '')->all(),
            // berkas laporan tidak disimpan di server (langsung diunduh), jadi riwayat menyediakan "ulangi" dengan parameter yang sama
            'jobs' => ReportJob::with('user:id,name')->when(!$request->user()->hasRole('super_admin', 'risk_admin', 'risk_manager'), fn ($q) => $q->where('user_id', $request->user()->id))->latest()->limit(20)->get(['id', 'user_id', 'type', 'format', 'status', 'params', 'error', 'created_at', 'finished_at']),
            'schedules' => \App\Models\ReportSchedule::with('user:id,name')->when(!$request->user()->hasRole('super_admin', 'risk_admin', 'risk_manager'), fn ($q) => $q->where('user_id', $request->user()->id))->orderBy('id')->get(),
        ]);
    }

    public function generate(Request $request, ReportService $service)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(ReportService::TYPES))],
            'format' => ['required', Rule::in(['pdf', 'xlsx'])],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'include_closed' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'in:10,20'],
            'period_from' => ['nullable', 'date_format:Y-m'], 'period_to' => ['nullable', 'date_format:Y-m'],
        ] + $this->filterRules());
        if (!in_array($data['format'], ReportService::FORMATS[$data['type']], true)) {
            return back()->withErrors(['format' => 'Format tidak tersedia untuk laporan ini.']);
        }
        $params = collect($data)->only([...ReportService::RISK_FILTERS, 'year', 'include_closed', 'limit', 'period_from', 'period_to'])->filter()->all();
        $job = ReportJob::create(['user_id' => $request->user()->id, 'type' => $data['type'], 'format' => $data['format'], 'params' => $params ?: null, 'status' => 'running']);
        try {
            $out = $service->render($data['type'], $data['format'], $request->user(), $params);
            $job->update(['status' => 'done', 'finished_at' => now(), 'path' => $out['file']]);
            AuditLog::record('exported', $job, [], 'reports.generate');
            return response($out['content'], 200, ['Content-Type' => $out['mime'], 'Content-Disposition' => 'attachment; filename="' . $out['file'] . '"']);
        } catch (\Throwable $e) {
            $job->update(['status' => 'failed', 'finished_at' => now(), 'error' => mb_substr($e->getMessage(), 0, 500)]);
            report($e);
            return back()->with('error', 'Gagal membuat laporan. Kesalahan telah dicatat; hubungi administrator bila berulang.');
        }
    }

    // ---- Laporan terjadwal (§13, skenario #24) ----
    public function storeSchedule(Request $request)
    {
        abort_if($request->user()->isReadOnly() && !$request->user()->hasRole('management'), 403);
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(ReportService::TYPES))],
            'format' => ['required', Rule::in(['pdf', 'xlsx'])],
            'frequency' => ['required', Rule::in(['monthly', 'weekly'])],
            'day' => ['required', 'integer', $request->input('frequency') === 'weekly' ? 'between:1,7' : 'between:1,28'],
            'recipients' => ['required', 'array', 'min:1', 'max:10'],
            'recipients.*' => ['required', 'email:rfc', 'max:160'],
            'unit_id' => ['nullable', 'integer'],
        ]);
        abort_unless(in_array($data['format'], ReportService::FORMATS[$data['type']], true), 422);
        \App\Models\ReportSchedule::create(collect($data)->except('unit_id')->all() + ['user_id' => $request->user()->id, 'active' => true, 'params' => array_filter(['unit_id' => $data['unit_id'] ?? null])]);
        return $this->ok('Jadwal laporan disimpan.');
    }

    /** Aturan filter risiko, sama dengan Risk Register (level/evaluasi boleh daftar dipisah koma). */
    private function filterRules(): array
    {
        $list = fn (array $allowed) => function (string $attr, $value, \Closure $fail) use ($allowed) {
            if (array_diff(explode(',', (string) $value), $allowed)) {
                $fail("Nilai {$attr} tidak valid.");
            }
        };
        return ['q' => ['nullable', 'string', 'max:100'], 'unit_id' => ['nullable', 'integer'], 'category_id' => ['nullable', 'integer'], 'owner_id' => ['nullable', 'integer'], 'objective_id' => ['nullable', 'integer'], 'process_id' => ['nullable', 'integer'],
            'level' => ['nullable', 'string', 'max:60', $list(array_keys(\App\Support\Scoring::LEVELS))], 'evaluation' => ['nullable', 'string', 'max:80', $list(array_keys(\App\Support\Scoring::EVALUATIONS))],
            'status' => ['nullable', Rule::in([...array_keys(config('manrisk.risk_statuses')), 'active'])]];
    }

    public function destroySchedule(Request $request, \App\Models\ReportSchedule $schedule)
    {
        abort_unless($schedule->user_id === $request->user()->id || $request->user()->hasRole('super_admin', 'risk_admin', 'risk_manager'), 403);
        $schedule->delete();
        return $this->ok('Jadwal laporan dihapus.');
    }
}

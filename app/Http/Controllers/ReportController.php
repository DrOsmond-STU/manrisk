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
            'units' => $this->unitOptions(),
            'jobs' => ReportJob::with('user:id,name')->latest()->limit(20)->get(),
        ]);
    }

    public function generate(Request $request, ReportService $service)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(ReportService::TYPES))],
            'format' => ['required', Rule::in(['pdf', 'xlsx'])],
            'unit_id' => ['nullable', 'integer'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'include_closed' => ['nullable', 'boolean'],
        ]);
        $params = collect($data)->only('unit_id', 'year', 'include_closed')->filter()->all();
        $job = ReportJob::create(['user_id' => $request->user()->id, 'type' => $data['type'], 'format' => $data['format'], 'params' => $params ?: null, 'status' => 'running']);
        try {
            $report = $service->data($data['type'], $request->user(), $params);
            $file = 'manrisk-' . $data['type'] . '-' . now()->format('Ymd-His') . '.' . $data['format'];
            $job->update(['status' => 'done', 'finished_at' => now(), 'path' => $file]);
            AuditLog::record('exported', $job, [], 'reports.generate');
            if ($data['format'] === 'xlsx') {
                return Excel::download(new ReportExport($data['type'], $report), $file);
            }
            $view = in_array($data['type'], ['executive', 'profile'], true) ? 'reports.executive' : (in_array($data['type'], ['action_plans', 'controls', 'kri', 'incidents'], true) ? 'reports.' . $data['type'] : 'reports.register');
            $pdf = Pdf::loadView($view, $report)->setPaper('a4', $data['type'] === 'register' ? 'landscape' : 'portrait');
            return $pdf->download($file);
        } catch (\Throwable $e) {
            $job->update(['status' => 'failed', 'finished_at' => now(), 'error' => mb_substr($e->getMessage(), 0, 500)]);
            report($e);
            return back()->with('error', 'Gagal membuat laporan: ' . $e->getMessage());
        }
    }
}

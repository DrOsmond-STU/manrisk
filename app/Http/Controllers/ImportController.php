<?php

namespace App\Http\Controllers;

use App\Models\Kri;
use App\Models\Risk;
use App\Services\AlertService;
use App\Services\ImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Impor Excel risiko & nilai KRI: unggah → pratinjau galat → konfirmasi. */
class ImportController extends Controller
{
    public function show(Request $request, string $type)
    {
        $this->guard($request, $type);
        return Inertia::render('Imports/Index', ['type' => $type, 'columns' => $type === 'kri' ? ImportService::KRI_COLUMNS : ImportService::RISK_COLUMNS, 'max' => ImportService::MAX_ROWS]);
    }

    public function template(Request $request, string $type, ImportService $svc)
    {
        $this->guard($request, $type);
        return response()->download($svc->template($type), "templat-impor-{$type}.xlsx")->deleteFileAfterSend();
    }

    public function preview(Request $request, string $type, ImportService $svc)
    {
        $this->guard($request, $type);
        $request->validate(['file' => ['required', 'file', 'max:5120', 'extensions:xlsx,xls,csv', 'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv,text/plain,application/zip,application/octet-stream']]);
        try {
            $rows = $svc->read($request->file('file')->getRealPath(), $type);
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => $e instanceof \RuntimeException ? $e->getMessage() : 'Berkas tidak dapat dibaca sebagai spreadsheet.']);
        }
        $result = $type === 'kri' ? $svc->validateKri($rows, $request->user()) : $svc->validateRisks($rows, $request->user());
        $token = Str::random(40);
        Cache::put("import:{$request->user()->id}:{$token}", ['type' => $type, 'valid' => $result['valid']], now()->addMinutes(30));
        return Inertia::render('Imports/Index', ['type' => $type, 'columns' => $type === 'kri' ? ImportService::KRI_COLUMNS : ImportService::RISK_COLUMNS, 'max' => ImportService::MAX_ROWS,
            'preview' => ['token' => $token, 'total' => count($rows), 'valid' => array_map(fn ($v) => ['line' => $v['line'], 'name' => $v['data']['name'] ?? $v['preview']['kri'], 'preview' => $v['preview']], $result['valid']), 'errors' => $result['errors']]]);
    }

    public function commit(Request $request, string $type, ImportService $svc, AlertService $alerts)
    {
        $this->guard($request, $type);
        $token = $request->validate(['token' => ['required', 'string', 'size:40']])['token'];
        $data = Cache::pull("import:{$request->user()->id}:{$token}");
        if (!$data || $data['type'] !== $type || !$data['valid']) {
            return redirect()->route('imports.show', $type)->with('error', 'Pratinjau kedaluwarsa atau tidak ada baris valid. Unggah ulang berkas.');
        }
        $n = $type === 'kri' ? $svc->commitKri($data['valid'], $request->user(), $alerts) : $svc->commitRisks($data['valid'], $request->user());
        \App\Models\AuditLog::record('imported', $request->user(), ['rows' => [null, $n], 'type' => [null, $type]], 'imports.commit');
        return redirect()->route($type === 'kri' ? 'kris.index' : 'risks.index', $type === 'kri' ? [] : ['status' => 'draft'])->with('success', "{$n} baris berhasil diimpor.");
    }

    private function guard(Request $request, string $type): void
    {
        abort_unless(in_array($type, ['risk', 'kri'], true), 404);
        $this->authorize('create', $type === 'kri' ? Kri::class : Risk::class);
    }
}

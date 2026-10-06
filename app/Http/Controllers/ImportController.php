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
        return $this->render($type);
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
        $preview = ['token' => $token, 'total' => count($rows), 'valid' => array_map(fn ($v) => ['line' => $v['line'], 'name' => $v['data']['name'] ?? $v['preview']['kri'], 'preview' => $v['preview']], $result['valid']), 'errors' => $result['errors']];
        Cache::put("import:{$request->user()->id}:{$token}", ['type' => $type, 'valid' => $result['valid'], 'preview' => $preview], now()->addMinutes(30));
        // token disimpan di sesi agar muat ulang (GET .../preview) menampilkan pratinjau yang sama, bukan 405
        session()->put("import_preview.{$type}", $token);
        return $this->render($type, $preview);
    }

    /** Muat ulang pratinjau dari token di sesi (pratinjau awal dirender langsung dari POST unggahan). */
    public function showPreview(Request $request, string $type)
    {
        $this->guard($request, $type);
        $token = session("import_preview.{$type}");
        $data = $token ? Cache::get("import:{$request->user()->id}:{$token}") : null;
        if (!$data || $data['type'] !== $type || empty($data['preview'])) {
            return redirect()->route('imports.show', $type)->with('error', 'Pratinjau kedaluwarsa. Unggah ulang berkas.');
        }
        return $this->render($type, $data['preview']);
    }

    public function commit(Request $request, string $type, ImportService $svc, AlertService $alerts)
    {
        $this->guard($request, $type);
        $token = $request->validate(['token' => ['required', 'string', 'size:40']])['token'];
        $data = Cache::pull("import:{$request->user()->id}:{$token}");
        session()->forget("import_preview.{$type}");
        if (!$data || $data['type'] !== $type || !$data['valid']) {
            return redirect()->route('imports.show', $type)->with('error', 'Pratinjau kedaluwarsa atau tidak ada baris valid. Unggah ulang berkas.');
        }
        if ($type === 'kri') {
            $n = $svc->commitKri($data['valid'], $request->user(), $alerts);
            $codes = Kri::whereIn('id', array_unique(array_column(array_column($data['valid'], 'data'), 'kri_id')))->orderBy('code')->pluck('code');
            \App\Models\AuditLog::record('imported', $request->user(), ['rows' => [null, $n], 'type' => [null, $type]], 'imports.commit');
            return redirect()->route('kris.index')->with('success', "{$n} nilai KRI berhasil diimpor untuk {$codes->count()} KRI: " . $codes->take(20)->implode(', ') . ($codes->count() > 20 ? ', …' : '') . '.');
        }
        $ids = $svc->commitRisks($data['valid'], $request->user());
        \App\Models\AuditLog::record('imported', $request->user(), ['rows' => [null, count($ids)], 'type' => [null, $type]], 'imports.commit');
        // tampilkan tepat risiko hasil impor di Risk Register
        return redirect()->route('risks.index', ['ids' => implode(',', array_slice($ids, 0, 500))])->with('success', count($ids) . ' risiko berhasil diimpor sebagai draft.');
    }

    private function render(string $type, ?array $preview = null)
    {
        return Inertia::render('Imports/Index', ['type' => $type, 'columns' => $type === 'kri' ? ImportService::KRI_COLUMNS : ImportService::RISK_COLUMNS, 'max' => ImportService::MAX_ROWS] + ($preview ? ['preview' => $preview] : []));
    }

    private function guard(Request $request, string $type): void
    {
        abort_unless(in_array($type, ['risk', 'kri'], true), 404);
        $this->authorize('create', $type === 'kri' ? Kri::class : Risk::class);
    }
}

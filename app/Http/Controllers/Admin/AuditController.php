<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\AuthLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Jejak audit (hanya baca) dan log autentikasi. */
class AuditController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', AuditLog::class);
        $f = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'action' => ['nullable', 'string', 'max:30'], 'subject' => ['nullable', 'string', 'max:60'], 'user_id' => ['nullable', 'integer'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $org = $request->user()->organization_id;
        $q = AuditLog::with('user:id,name,role')->where('organization_id', $org)->latest('id');
        if (!empty($f['q'])) {
            $q->where(fn ($w) => $w->where('subject_label', 'like', "%{$f['q']}%")->orWhere('context', 'like', "%{$f['q']}%"));
        }
        if (!empty($f['action'])) {
            $q->where('action', $f['action']);
        }
        if (!empty($f['subject'])) {
            $q->where('subject_type', 'like', '%' . $f['subject']);
        }
        if (!empty($f['user_id'])) {
            $q->where('user_id', $f['user_id']);
        }
        if (!empty($f['from'])) {
            $q->where('created_at', '>=', $f['from']);
        }
        if (!empty($f['to'])) {
            $q->where('created_at', '<=', $f['to'] . ' 23:59:59');
        }
        return Inertia::render('Admin/Audit', [
            'logs' => $q->paginate(50)->withQueryString()->through(fn ($l) => ['id' => $l->id, 'action' => $l->action, 'subject' => class_basename($l->subject_type), 'subject_id' => $l->subject_id, 'label' => $l->subject_label, 'changes' => $l->changes, 'context' => $l->context, 'ip' => $l->ip, 'created_at' => $l->created_at, 'user' => $l->user?->name]),
            'filters' => $f,
            'actions' => AuditLog::where('organization_id', $org)->select('action')->distinct()->orderBy('action')->pluck('action'),
            'subjects' => AuditLog::where('organization_id', $org)->select('subject_type')->distinct()->pluck('subject_type')->map(fn ($s) => class_basename($s))->unique()->sort()->values(),
            'users' => $this->userOptions(),
            'auth_logs' => $request->user()->hasRole('super_admin', 'risk_admin', 'auditor') ? AuthLog::with('user:id,name')->where('organization_id', $org)->latest('id')->limit(200)->get() : [],
        ]);
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', AuditLog::class);
        $f = $request->validate(['action' => ['nullable', 'string', 'max:30'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'user_id' => ['nullable', 'integer']]);
        $org = $request->user()->organization_id;
        $q = AuditLog::with('user:id,name')->where('organization_id', $org)->orderBy('id')
            ->when($f['action'] ?? null, fn ($x, $v) => $x->where('action', $v))->when($f['user_id'] ?? null, fn ($x, $v) => $x->where('user_id', $v))
            ->when($f['from'] ?? null, fn ($x, $v) => $x->where('created_at', '>=', $v))->when($f['to'] ?? null, fn ($x, $v) => $x->where('created_at', '<=', $v . ' 23:59:59'));
        AuditLog::record('exported', $request->user(), ['audit_csv' => [null, $f]], 'audit.export');
        $safe = fn ($v) => \App\Exports\ReportExport::safe(is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string) $v);
        return response()->streamDownload(function () use ($q, $safe) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['id', 'waktu', 'pengguna', 'aksi', 'objek', 'id_objek', 'label', 'perubahan', 'konteks', 'ip', 'user_agent']);
            $q->chunk(1000, function ($rows) use ($out, $safe) {
                foreach ($rows as $l) {
                    fputcsv($out, array_map($safe, [$l->id, $l->created_at?->format('Y-m-d H:i:s'), $l->user?->name ?? 'sistem', $l->action, $l->subject_type, $l->subject_id, $l->subject_label, $l->changes, $l->context, $l->ip, $l->user_agent]));
                }
            });
            fclose($out);
        }, 'audit-trail-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

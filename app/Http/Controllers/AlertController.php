<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AlertController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->validate(['severity' => ['nullable', 'in:info,warning,critical'], 'open' => ['nullable', 'boolean']]);
        $q = Alert::with('handler:id,name')->latest();
        if (!empty($f['severity'])) {
            $q->where('severity', $f['severity']);
        }
        if (($f['open'] ?? '1') !== '0') {
            $q->whereNull('handled_at');
        }
        return Inertia::render('Alerts/Index', ['alerts' => $q->paginate(30)->withQueryString(), 'filters' => $f, 'stats' => Alert::whereNull('handled_at')->selectRaw('severity, count(*) n')->groupBy('severity')->pluck('n', 'severity')]);
    }

    public function read(Request $request, Alert $alert)
    {
        $alert->update(['read_at' => $alert->read_at ?? now()]);
        return back();
    }

    public function readAll(Request $request)
    {
        Alert::whereNull('read_at')->update(['read_at' => now()]);
        return back()->with('success', 'Semua peringatan ditandai dibaca.');
    }

    public function handle(Request $request, Alert $alert)
    {
        abort_if($request->user()->isReadOnly(), 403);
        $alert->update(['handled_at' => now(), 'handled_by' => $request->user()->id, 'read_at' => $alert->read_at ?? now()]);
        return back()->with('success', 'Peringatan ditandai selesai.');
    }

    /** Daftar ringkas untuk lonceng notifikasi (JSON). */
    public function latest(Request $request)
    {
        return response()->json(Alert::whereNull('handled_at')->latest()->limit(10)->get(['id', 'type', 'severity', 'title', 'link', 'read_at', 'created_at']));
    }
}

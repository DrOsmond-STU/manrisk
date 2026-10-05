<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use Illuminate\Http\Request;
use App\Support\UnitScope;
use Inertia\Inertia;

class AlertController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->validate(['severity' => ['nullable', 'in:info,warning,critical'], 'open' => ['nullable', 'boolean']]);
        $q = UnitScope::morph(Alert::with('handler:id,name'), $request->user())->latest();
        if (!empty($f['severity'])) {
            $q->where('severity', $f['severity']);
        }
        if (($f['open'] ?? '1') !== '0') {
            $q->whereNull('handled_at');
        }
        return Inertia::render('Alerts/Index', ['alerts' => $q->paginate(30)->withQueryString(), 'filters' => $f, 'stats' => UnitScope::morph(Alert::query(), $request->user())->whereNull('handled_at')->selectRaw('severity, count(*) n')->groupBy('severity')->pluck('n', 'severity')]);
    }

    public function read(Request $request, Alert $alert)
    {
        $this->guard($request, $alert);
        $alert->update(['read_at' => $alert->read_at ?? now()]);
        return back();
    }

    public function readAll(Request $request)
    {
        UnitScope::morph(Alert::query(), $request->user())->whereNull('read_at')->update(['read_at' => now()]);
        return back()->with('success', 'Semua peringatan ditandai dibaca.');
    }

    public function handle(Request $request, Alert $alert)
    {
        abort_if($request->user()->isReadOnly(), 403);
        $this->guard($request, $alert);
        $alert->update(['handled_at' => now(), 'handled_by' => $request->user()->id, 'read_at' => $alert->read_at ?? now()]);
        return back()->with('success', 'Peringatan ditandai selesai.');
    }

    /** Daftar ringkas untuk lonceng notifikasi (JSON). */
    public function latest(Request $request)
    {
        return response()->json(UnitScope::morph(Alert::query(), $request->user())->whereNull('handled_at')->latest()->limit(10)->get(['id', 'type', 'severity', 'title', 'link', 'read_at', 'created_at']));
    }

    private function guard(Request $request, Alert $alert): void
    {
        abort_unless(UnitScope::morph(Alert::whereKey($alert->id), $request->user())->exists(), 403);
    }
}

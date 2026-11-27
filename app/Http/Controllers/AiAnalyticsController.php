<?php

namespace App\Http\Controllers;

use App\Models\AiDashboard;
use App\Services\Ai\AnalyticsEngine;
use App\Services\Ai\AnalyticsPlanner;
use App\Services\Ai\Datasets;
use App\Services\AiService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * AI Analitik: asisten mandiri yang mengolah data aplikasi dan menyajikan dashboard sesuai
 * permintaan pengguna. AI hanya menyusun spesifikasi; perhitungan dilakukan aplikasi atas data
 * dalam cakupan pengguna. Dashboard dapat disimpan dan dibagikan ke organisasi; data tetap
 * dihitung menurut hak akses masing-masing penampil.
 */
class AiAnalyticsController extends Controller
{
    private const EXAMPLES = [
        'Dashboard risiko eksekutif',
        'Jumlah risiko per unit kerja dan level',
        'Tren insiden per bulan 12 bulan terakhir dan total kerugian tahun ini',
        'Action plan terlambat per unit',
        '10 risiko dengan skor residual tertinggi',
        'Komposisi efektivitas kontrol',
        'Total eksposur residual per kategori',
        'Status KRI dan daftar KRI yang melewati ambang',
    ];

    private function guard(Request $request): void
    {
        abort_if($request->user()->role === 'auditor', 403, 'Auditor tidak menggunakan AI Assistant (§3.2).');
    }

    private function engine(Request $request): AnalyticsEngine
    {
        return new AnalyticsEngine(new Datasets($request->user()));
    }

    private function visible(Request $request)
    {
        return AiDashboard::query()->where(fn ($q) => $q->where('user_id', $request->user()->id)->orWhere('shared', true));
    }

    public function index(Request $request, AiService $ai)
    {
        $this->guard($request);
        $me = $request->user();
        return Inertia::render('Ai/Analytics', [
            'enabled' => $ai->available(), 'remaining' => $ai->remaining($me), 'provider' => $ai->hasProvider() ? config('manrisk.ai.model') : 'mode lokal (tanpa kunci API)',
            'examples' => self::EXAMPLES,
            'dashboards' => $this->visible($request)->with('user:id,name')->latest('updated_at')->limit(50)->get()->map(fn ($d) => ['id' => $d->id, 'title' => $d->title, 'prompt' => $d->prompt, 'widgets' => $d->widgets, 'shared' => $d->shared,
                'mine' => $d->user_id === $me->id, 'owner' => $d->user?->name, 'updated_at' => $d->updated_at?->toIso8601String()]),
            'open' => $request->integer('d') ?: null,
            'datasets' => collect((new Datasets($me))->defs())->map(fn ($d, $k) => ['key' => $k, 'label' => $d['label']])->values(),
        ]);
    }

    public function plan(Request $request, AiService $ai)
    {
        $this->guard($request);
        $data = $request->validate(['prompt' => ['required', 'string', 'min:3', 'max:1000']]);
        $engine = $this->engine($request);
        $plan = (new AnalyticsPlanner($engine, $ai))->plan($data['prompt']);
        return response()->json(['title' => $plan['spec']['title'], 'model' => $plan['model'], 'local' => $plan['local'], 'remaining' => $plan['remaining']] + $engine->runAll($plan['spec']['widgets']));
    }

    public function run(Request $request)
    {
        $this->guard($request);
        $data = $request->validate(['widgets' => ['required', 'array', 'min:1', 'max:' . AnalyticsEngine::MAX_WIDGETS], 'widgets.*' => ['array']]);
        return response()->json($this->engine($request)->runAll($data['widgets']));
    }

    private function payload(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'prompt' => ['nullable', 'string', 'max:1000'],
            'shared' => ['boolean'],
            'widgets' => ['required', 'array', 'min:1', 'max:' . AnalyticsEngine::MAX_WIDGETS],
            'widgets.*' => ['array'],
        ]);
        $engine = $this->engine($request);
        $widgets = array_values(array_filter(array_map(fn ($w) => $engine->normalize($w), $data['widgets'])));
        abort_if(!$widgets, 422, 'Tidak ada widget yang valid.');
        return ['title' => strip_tags($data['title']), 'prompt' => isset($data['prompt']) ? strip_tags($data['prompt']) : null, 'shared' => (bool) ($data['shared'] ?? false), 'widgets' => $widgets];
    }

    public function store(Request $request)
    {
        $this->guard($request);
        $d = AiDashboard::create($this->payload($request) + ['user_id' => $request->user()->id]);
        return response()->json(['id' => $d->id, 'message' => 'Dashboard disimpan.']);
    }

    public function update(Request $request, AiDashboard $dashboard)
    {
        $this->guard($request);
        abort_unless($dashboard->user_id === $request->user()->id, 403, 'Hanya pembuat yang dapat mengubah dashboard ini.');
        $dashboard->update($this->payload($request));
        return response()->json(['id' => $dashboard->id, 'message' => 'Dashboard diperbarui.']);
    }

    public function destroy(Request $request, AiDashboard $dashboard)
    {
        $this->guard($request);
        abort_unless($dashboard->user_id === $request->user()->id || $request->user()->role === 'super_admin', 403, 'Hanya pembuat yang dapat menghapus dashboard ini.');
        $dashboard->delete();
        return response()->json(['message' => 'Dashboard dihapus.']);
    }
}

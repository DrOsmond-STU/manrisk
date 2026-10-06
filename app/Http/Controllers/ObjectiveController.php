<?php

namespace App\Http\Controllers;

use App\Models\Objective;
use App\Models\Process;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Sasaran strategis, program, dan proses bisnis (pemetaan tujuan ↔ risiko). */
class ObjectiveController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Objective::class);
        return Inertia::render('Organization/Objectives', [
            // Jumlah risiko mengikuti cakupan unit pengguna agar sama dengan daftar di Risk Register
            'objectives' => Objective::withCount(['risks' => fn ($q) => $this->scopeUnits($q)->where('status', '!=', 'closed')])->with(['risks' => fn ($q) => $this->scopeUnits($q)->where('status', '!=', 'closed')->select('id', 'objective_id', 'unit_id', 'residual_score', 'residual_level')])->orderBy('sort')->orderBy('code')->get()
                ->map(fn ($o) => $o->only('id', 'code', 'name', 'kpi', 'period', 'sort', 'active', 'risks_count') + ['max_score' => (int) $o->risks->max('residual_score'), 'high' => $o->risks->whereIn('residual_level', ['high', 'very_high'])->count()]),
            'programs' => Program::with(['objective:id,code,name', 'unit:id,name'])->withCount('processes')->orderBy('name')->get(),
            'processes' => Process::with(['program:id,name', 'unit:id,name'])->withCount(['risks' => fn ($q) => $this->scopeUnits($q)->where('status', '!=', 'closed')])->orderBy('name')->get(),
            'can_create_risk' => auth()->user()->can('create', \App\Models\Risk::class),
            'units' => $this->unitOptions(),
            'can' => ['write' => auth()->user()->can('create', Objective::class), 'delete' => auth()->user()->can('delete', new Objective())],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Objective::class);
        Objective::create($this->validated($request));
        return $this->ok('Sasaran ditambahkan.');
    }

    public function update(Request $request, Objective $objective)
    {
        $this->authorize('update', $objective);
        $objective->update($this->validated($request, $objective));
        return $this->ok('Sasaran diperbarui.');
    }

    public function destroy(Objective $objective)
    {
        $this->authorize('delete', $objective);
        if ($objective->risks()->exists()) {
            return back()->with('error', 'Sasaran masih dipetakan ke risiko.');
        }
        $objective->delete();
        return $this->ok('Sasaran dihapus.');
    }

    private function validated(Request $request, ?Objective $o = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('objectives')->where('organization_id', $request->user()->organization_id)->ignore($o?->id)],
            'name' => ['required', 'string', 'max:255'],
            'kpi' => ['nullable', 'string', 'max:255'],
            'period' => ['nullable', 'string', 'max:20'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ], ['code.unique' => 'Kode sudah digunakan (termasuk oleh sasaran yang sudah dihapus); gunakan kode lain.']);
    }

    // ---- Program ----
    public function storeProgram(Request $request)
    {
        $this->authorize('create', Program::class);
        Program::create($this->programRules($request));
        return $this->ok('Program ditambahkan.');
    }

    public function updateProgram(Request $request, Program $program)
    {
        $this->authorize('update', $program);
        $program->update($this->programRules($request));
        return $this->ok('Program diperbarui.');
    }

    public function destroyProgram(Program $program)
    {
        $this->authorize('delete', $program);
        if ($program->processes()->exists()) {
            return back()->with('error', 'Program masih memiliki proses bisnis; pindahkan atau hapus prosesnya dahulu.');
        }
        $program->delete();
        return $this->ok('Program dihapus.');
    }

    private function programRules(Request $request): array
    {
        $org = $request->user()->organization_id;
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'objective_id' => ['nullable', Rule::exists('objectives', 'id')->where('organization_id', $org)],
            'unit_id' => ['nullable', Rule::exists('org_units', 'id')->where('organization_id', $org)],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ]);
    }

    // ---- Proses ----
    public function storeProcess(Request $request)
    {
        $this->authorize('create', Process::class);
        Process::create($this->processRules($request));
        return $this->ok('Proses ditambahkan.');
    }

    public function updateProcess(Request $request, Process $process)
    {
        $this->authorize('update', $process);
        $process->update($this->processRules($request));
        return $this->ok('Proses diperbarui.');
    }

    public function destroyProcess(Process $process)
    {
        $this->authorize('delete', $process);
        if ($process->risks()->exists()) {
            return back()->with('error', 'Proses masih dipakai oleh risiko.');
        }
        $process->delete();
        return $this->ok('Proses dihapus.');
    }

    private function processRules(Request $request): array
    {
        $org = $request->user()->organization_id;
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'program_id' => ['nullable', Rule::exists('programs', 'id')->where('organization_id', $org)],
            'unit_id' => ['nullable', Rule::exists('org_units', 'id')->where('organization_id', $org)],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}

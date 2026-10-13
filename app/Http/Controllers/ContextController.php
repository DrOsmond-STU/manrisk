<?php

namespace App\Http\Controllers;

use App\Models\Consultation;
use App\Models\ContextFactor;
use App\Models\Scope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** ISO 31000 §6.3: ruang lingkup, konteks internal/eksternal (SWOT), komunikasi & konsultasi. */
class ContextController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Scope::class);
        return Inertia::render('Context/Index', [
            'scopes' => Scope::with(['unit:id,name', 'creator:id,name'])->withCount(['factors', 'consultations', 'risks'])->latest()->get(),
            'kks_types' => config('manrisk.kks_types'),
            'decision_organs' => config('manrisk.decision_organs'),
            'factors' => ContextFactor::with('scope:id,name')->orderBy('kind')->orderBy('nature')->get(),
            'consultations' => Consultation::with('scope:id,name')->orderByDesc('held_on')->get(),
            'units' => $this->unitOptions(),
            'can' => ['write' => auth()->user()->can('create', Scope::class), 'delete' => auth()->user()->can('delete', new Scope())],
        ]);
    }

    public function storeScope(Request $request)
    {
        $this->authorize('create', Scope::class);
        Scope::create($this->scopeRules($request) + ['created_by' => $request->user()->id]);
        return $this->ok('Ruang lingkup disimpan.');
    }

    public function updateScope(Request $request, Scope $scope)
    {
        $this->authorize('update', $scope);
        $scope->update($this->scopeRules($request));
        return $this->ok('Ruang lingkup diperbarui.');
    }

    public function destroyScope(Scope $scope)
    {
        $this->authorize('delete', $scope);
        $scope->delete();
        return $this->ok('Ruang lingkup dihapus.');
    }

    private function scopeRules(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'objective' => ['nullable', 'string', 'max:2000'],
            'boundaries' => ['nullable', 'string', 'max:2000'],
            'period' => ['nullable', 'string', 'max:40'],
            'unit_id' => ['nullable', Rule::exists('org_units', 'id')->where('organization_id', $request->user()->organization_id)],
            'area' => ['nullable', 'string', 'max:2000'],
            'kind' => ['nullable', Rule::in(['regular', 'kks'])],
            'kks_type' => ['nullable', 'required_if:kind,kks', Rule::in(array_keys(config('manrisk.kks_types')))],
            'transaction_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999'],
            'decision_organ' => ['nullable', Rule::in(array_keys(config('manrisk.decision_organs')))],
            'decision_date' => ['nullable', 'date'],
            'conclusion' => ['nullable', 'string', 'max:4000'],
        ], ['kks_type.required_if' => 'Jenis KKS wajib dipilih untuk kajian Kegiatan Korporasi Signifikan.']);
        $data['kind'] = $data['kind'] ?? 'regular';
        if ($data['kind'] !== 'kks') {
            $data = array_merge($data, ['kks_type' => null, 'transaction_value' => null, 'decision_organ' => null, 'decision_date' => null, 'conclusion' => null]);
        }
        return $data;
    }

    /** Kajian risiko Kegiatan Korporasi Signifikan (PER-2/MBU/03/2023) sebagai PDF untuk organ pemutus. */
    public function kksPdf(Scope $scope)
    {
        $this->authorize('view', $scope);
        abort_if($scope->kind !== 'kks', 404);
        $risks = $scope->risks()->with(['category:id,name,bumn_code', 'owner:id,name', 'unit:id,name', 'actionPlans:id,risk_id,code,title,due_date,status,budget'])->orderByDesc('residual_score')->get();
        $scoring = app(\App\Support\Scoring::class);
        \App\Models\AuditLog::record('exported', $scope, [], 'kks.pdf');
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.kks', ['title' => 'Kajian Risiko Kegiatan Korporasi Signifikan', 'org' => auth()->user()->organization?->name,
            'generated_at' => now(), 'by' => auth()->user()->name, 'filter_label' => null, 's' => $scope->load('unit:id,name', 'factors', 'consultations'), 'risks' => $risks,
            'level_labels' => \App\Support\Scoring::levels(), 'inherentLevel' => fn ($r) => $scoring->level($r->inherent_l, $r->inherent_i),
            'kks_types' => config('manrisk.kks_types'), 'organs' => config('manrisk.decision_organs'), 'settings' => auth()->user()->organization?->settings ?? []]);
        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="kajian-risiko-kks-' . $scope->id . '.pdf"']);
    }

    public function storeFactor(Request $request)
    {
        $this->authorize('create', Scope::class);
        ContextFactor::create($this->factorRules($request));
        return $this->ok('Faktor konteks ditambahkan.');
    }

    public function updateFactor(Request $request, ContextFactor $factor)
    {
        $this->authorize('update', $factor->scope ?? new Scope(['organization_id' => $factor->organization_id]));
        $factor->update($this->factorRules($request));
        return $this->ok('Faktor konteks diperbarui.');
    }

    public function destroyFactor(ContextFactor $factor)
    {
        $this->authorize('delete', $factor->scope ?? new Scope(['organization_id' => $factor->organization_id]));
        $factor->delete();
        return $this->ok('Faktor konteks dihapus.');
    }

    private function factorRules(Request $request): array
    {
        return $request->validate([
            'scope_id' => ['nullable', Rule::exists('scopes', 'id')->where('organization_id', $request->user()->organization_id)],
            'kind' => ['required', Rule::in(['internal', 'external'])],
            'factor' => ['required', 'string', 'max:120'],
            'condition' => ['required', 'string', 'max:2000'],
            'nature' => ['required', Rule::in(['strength', 'weakness', 'opportunity', 'threat'])],
        ]);
    }

    public function storeConsultation(Request $request)
    {
        $this->authorize('create', Scope::class);
        Consultation::create($this->consultationRules($request));
        return $this->ok('Konsultasi disimpan.');
    }

    public function updateConsultation(Request $request, Consultation $consultation)
    {
        $this->authorize('update', $consultation->scope ?? new Scope(['organization_id' => $consultation->organization_id]));
        $consultation->update($this->consultationRules($request));
        return $this->ok('Konsultasi diperbarui.');
    }

    public function destroyConsultation(Consultation $consultation)
    {
        $this->authorize('delete', $consultation->scope ?? new Scope(['organization_id' => $consultation->organization_id]));
        $consultation->delete();
        return $this->ok('Konsultasi dihapus.');
    }

    private function consultationRules(Request $request): array
    {
        return $request->validate([
            'scope_id' => ['nullable', Rule::exists('scopes', 'id')->where('organization_id', $request->user()->organization_id)],
            'title' => ['required', 'string', 'max:255'],
            'held_on' => ['required', 'date'],
            'participants' => ['nullable', 'string', 'max:2000'],
            'decisions' => ['nullable', 'string', 'max:4000'],
            'status' => ['required', Rule::in(['planned', 'done'])],
        ]);
    }
}

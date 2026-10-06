<?php

namespace App\Http\Controllers;

use App\Models\OrgUnit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class OrgUnitController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', OrgUnit::class);
        $units = OrgUnit::withCount(['risks' => fn ($q) => $q->where('status', '!=', 'closed'), 'users' => fn ($q) => $q->where('active', true)])->with('head:id,name')->orderBy('path')->orderBy('sort')->get();
        // Total termasuk sub-unit — sama dengan hasil /risks?unit_id= yang menyertakan sub-unit
        $children = $units->groupBy('parent_id');
        $total = function ($u) use (&$total, $children) {
            return $u->risks_count + ($children[$u->id] ?? collect())->sum(fn ($c) => $total($c));
        };
        return Inertia::render('Organization/Units', [
            'units' => $units->map(fn ($u) => $u->only('id', 'parent_id', 'name', 'code', 'type', 'level', 'active', 'head_user_id', 'risks_count', 'users_count') + ['head' => $u->head?->name, 'risks_total' => $total($u)]),
            'users' => $this->userOptions(),
            'can' => ['write' => auth()->user()->can('create', OrgUnit::class)],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', OrgUnit::class);
        $data = $this->validated($request);
        $unit = OrgUnit::create($data);
        $unit->refreshPath();
        return $this->ok("Unit {$unit->name} ditambahkan.");
    }

    public function update(Request $request, OrgUnit $unit)
    {
        $this->authorize('update', $unit);
        $data = $this->validated($request, $unit);
        if (($data['parent_id'] ?? null) && in_array((int) $data['parent_id'], [$unit->id, ...$unit->descendantIds()], true)) {
            return back()->withErrors(['parent_id' => 'Induk tidak boleh unit itu sendiri atau sub-unitnya.']);
        }
        $unit->update($data);
        $unit->refreshPath();
        return $this->ok('Unit diperbarui.');
    }

    public function destroy(OrgUnit $unit)
    {
        $this->authorize('delete', $unit);
        if ($unit->children()->exists() || $unit->risks()->withTrashed()->exists()) {
            return back()->with('error', 'Unit masih memiliki sub-unit atau risiko; nonaktifkan saja.');
        }
        // Unit yang masih dirujuk data lain tidak dihapus agar akses & riwayat tidak terputus
        $refs = ['users' => 'pengguna', 'processes' => 'proses bisnis', 'programs' => 'program', 'controls' => 'kontrol', 'action_plans' => 'action plan', 'incidents' => 'insiden', 'improvements' => 'perbaikan', 'scopes' => 'ruang lingkup'];
        $used = collect($refs)->filter(fn ($label, $table) => \Illuminate\Support\Facades\Schema::hasColumn($table, 'unit_id') && \Illuminate\Support\Facades\DB::table($table)->where('unit_id', $unit->id)->exists())->values();
        if ($used->isNotEmpty()) {
            return back()->with('error', 'Unit masih dipakai oleh ' . $used->implode(', ') . '; pindahkan dahulu atau nonaktifkan unit.');
        }
        $unit->delete();
        return $this->ok('Unit dihapus.');
    }

    private function validated(Request $request, ?OrgUnit $unit = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9._-]+$/i', Rule::unique('org_units')->where('organization_id', $request->user()->organization_id)->ignore($unit?->id)],
            'type' => ['required', Rule::in(array_keys(config('manrisk.unit_types')))],
            'parent_id' => ['nullable', Rule::exists('org_units', 'id')->where('organization_id', $request->user()->organization_id)],
            'head_user_id' => ['nullable', Rule::exists('users', 'id')->where('organization_id', $request->user()->organization_id)],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'active' => ['nullable', 'boolean'],
        ], ['code.unique' => 'Kode sudah digunakan (termasuk oleh unit yang sudah dihapus); gunakan kode lain.']);
    }
}

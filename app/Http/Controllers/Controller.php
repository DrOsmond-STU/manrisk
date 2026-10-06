<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    /** Batasi query ke unit yang boleh diakses pengguna bercakupan unit. */
    protected function scopeUnits(Builder|Relation $query, string $column = 'unit_id', ?Request $request = null): Builder|Relation
    {
        $user = ($request ?? request())->user();
        $ids = $user?->accessibleUnitIds();
        if ($ids !== null) {
            $query->whereIn($column, $ids);
        }
        return $query;
    }

    /** Daftar unit sebagai opsi select (terurut hierarki). */
    protected function unitOptions(bool $scoped = false): array
    {
        $ids = $scoped ? request()->user()?->accessibleUnitIds() : null;
        return \App\Models\OrgUnit::orderBy('path')->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))->get(['id', 'name', 'code', 'level', 'parent_id'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => str_repeat('— ', $u->level) . $u->name, 'code' => $u->code, 'parent_id' => $u->parent_id])->all();
    }

    protected function userOptions(array $roles = []): array
    {
        return \App\Models\User::where('active', true)->when($roles, fn ($q) => $q->whereIn('role', $roles))
            ->orderBy('name')->get(['id', 'name', 'role', 'unit_id'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'role' => $u->roleLabel(), 'unit_id' => $u->unit_id])->all();
    }

    protected function riskOptions(): array
    {
        return $this->scopeUnits(\App\Models\Risk::query())->where('status', '!=', 'closed')->orderBy('code')->get(['id', 'code', 'name', 'unit_id', 'owner_id'])->all();
    }

    protected function ok(string $message)
    {
        return back()->with('success', $message);
    }
}

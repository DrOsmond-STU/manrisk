<?php

namespace App\Http\Controllers;

use App\Models\Kri;
use App\Models\KriValue;
use App\Services\AlertService;
use App\Support\Numbering;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Key Risk Indicator & early warning (§4.12). */
class KriController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Kri::class);
        $kris = Kri::with(['risk:id,code,name,unit_id', 'owner:id,name', 'values' => fn ($q) => $q->orderByDesc('period')->limit(12)])->orderByRaw("case status when 'critical' then 0 when 'warning' then 1 else 2 end")->orderBy('code')->get();
        if ($request->user()->isUnitScoped()) {
            $ids = $request->user()->accessibleUnitIds();
            $kris = $kris->filter(fn ($k) => !$k->risk || in_array($k->risk->unit_id, $ids, true))->values();
        }
        return Inertia::render('Kris/Index', [
            'kris' => $kris->map(fn ($k) => $k->toArray() + ['series' => $k->values->sortBy('period')->values()->map(fn ($v) => ['period' => $v->period->format('Y-m'), 'value' => (float) $v->value])]),
            'stats' => ['total' => $kris->count(), 'critical' => $kris->where('status', 'critical')->count(), 'warning' => $kris->where('status', 'warning')->count(), 'normal' => $kris->where('status', 'normal')->count()],
            'risks' => $this->riskOptions(),
            'users' => $this->userOptions(),
            'can' => ['write' => $request->user()->can('create', Kri::class), 'delete' => $request->user()->can('delete', new Kri())],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Kri::class);
        $kri = Kri::create($this->rules($request) + ['code' => Numbering::next(Kri::class, 'KRI', null, false), 'status' => 'normal']);
        return $this->ok("KRI {$kri->code} ditambahkan.");
    }

    public function update(Request $request, Kri $kri)
    {
        $this->authorize('update', $kri);
        $kri->fill($this->rules($request));
        $kri->status = $kri->statusFor($kri->last_value);
        $kri->save();
        return $this->ok('KRI diperbarui.');
    }

    public function destroy(Kri $kri)
    {
        $this->authorize('delete', $kri);
        $kri->delete();
        return $this->ok('KRI dihapus.');
    }

    /** Input nilai periode → status & peringatan dini otomatis. */
    public function value(Request $request, Kri $kri, AlertService $alerts)
    {
        $this->authorize('update', $kri);
        $data = $request->validate(['period' => ['required', 'date_format:Y-m'], 'value' => ['required', 'numeric', 'between:-999999999,999999999'], 'source_ref' => ['nullable', 'string', 'max:120']]);
        $period = $data['period'] . '-01';
        $existing = KriValue::where('kri_id', $kri->id)->whereDate('period', $period)->first();
        $attrs = ['value' => $data['value'], 'entered_by' => $request->user()->id, 'source_ref' => $data['source_ref'] ?? null];
        $existing ? $existing->update($attrs) : KriValue::create(['kri_id' => $kri->id, 'period' => $period] + $attrs);
        $latest = $kri->values()->orderByDesc('period')->first();
        $prev = $kri->status;
        $kri->update(['last_value' => $latest->value, 'status' => $kri->statusFor((float) $latest->value)]);
        $alerts->checkKri($kri->fresh(), $prev);
        return $this->ok('Nilai KRI disimpan.');
    }

    private function rules(Request $request): array
    {
        $org = $request->user()->organization_id;
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:40'],
            'risk_id' => ['nullable', Rule::exists('risks', 'id')->where('organization_id', $org)],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->where('organization_id', $org)],
            'source' => ['required', Rule::in(['manual', 'api', 'import'])],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly', 'quarterly'])],
            'direction' => ['required', Rule::in(['up_bad', 'down_bad'])],
            'threshold_warn' => ['required', 'numeric'],
            'threshold_crit' => ['required', 'numeric', $request->direction === 'down_bad' ? 'lte:threshold_warn' : 'gte:threshold_warn'],
            'decimals' => ['nullable', 'integer', 'between:0,4'],
            'active' => ['nullable', 'boolean'],
        ]);
    }
}

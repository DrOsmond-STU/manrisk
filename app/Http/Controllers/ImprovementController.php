<?php

namespace App\Http\Controllers;

use App\Models\FrameworkItem;
use App\Models\Improvement;
use App\Models\Lesson;
use App\Support\Numbering;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Continual improvement, lesson learned, dan kepatuhan kerangka ISO 31000 (§4.20, §4.21). */
class ImprovementController extends Controller
{
    public const SOURCES = ['incident' => 'Insiden', 'audit' => 'Temuan Audit', 'control_failure' => 'Kegagalan Kontrol', 'kri_breach' => 'Pelanggaran KRI', 'treatment' => 'Evaluasi Treatment', 'trend' => 'Tren Risiko', 'lesson' => 'Lesson Learned', 'review' => 'Hasil Review', 'other' => 'Lainnya'];

    /** Jenis sumber yang dapat ditautkan (alias morph). */
    public const SUBJECT_TYPES = ['control' => 'Kontrol', 'kri' => 'KRI', 'incident' => 'Insiden', 'review' => 'Review'];

    public function index(Request $request)
    {
        $this->authorize('viewAny', Improvement::class);
        $f = $request->validate(['status' => ['nullable', Rule::in(['open', 'in_progress', 'done', 'active'])], 'source_type' => ['nullable', Rule::in(array_keys(self::SOURCES))],
            'subject_type' => ['nullable', Rule::in(array_keys(self::SUBJECT_TYPES))], 'subject_id' => ['nullable', 'integer'], 'risk_id' => ['nullable', 'integer'], 'id' => ['nullable', 'integer']]);
        $user = $request->user();
        $q = $this->scoped($user)->with(['pic:id,name', 'unit:id,name', 'risk:id,code,name,unit_id', 'subject'])->orderByRaw("case status when 'open' then 0 when 'in_progress' then 1 else 2 end")->orderBy('due_date');
        if (!empty($f['status'])) {
            $f['status'] === 'active' ? $q->where('status', '!=', 'done') : $q->where('status', $f['status']);
        }
        foreach (['source_type', 'subject_type', 'subject_id', 'risk_id', 'id'] as $k) {
            if (!empty($f[$k])) {
                $q->where($k, $f[$k]);
            }
        }
        $items = $q->get()->map(fn ($i) => collect($i->toArray())->except('subject')->all() + ['subject' => $this->subjectInfo($i), 'can_update' => $user->can('update', $i)]);
        $subject = !empty($f['subject_type']) && !empty($f['subject_id']) ? (Relation::getMorphedModel($f['subject_type']))::find($f['subject_id']) : null;
        return Inertia::render('Improvements/Index', [
            'items' => $items,
            'filters' => $f,
            'filter_labels' => array_filter(['subject' => $subject ? self::SUBJECT_TYPES[$f['subject_type']] . ' ' . ($subject->code ?? "#{$subject->id}") . ' · ' . ($subject->name ?? $subject->title ?? $subject->period ?? '') : null,
                'risk' => !empty($f['risk_id']) ? \App\Models\Risk::find($f['risk_id'])?->code : null]),
            'lessons' => \App\Support\UnitScope::morph(Lesson::with(['creator:id,name', 'subject']), $user)->latest()->limit(100)->get()->map(fn ($l) => ['id' => $l->id, 'text' => $l->text, 'created_at' => $l->created_at, 'creator' => $l->creator?->name,
                'subject' => $l->subject ? ['type' => $l->subject_type, 'id' => $l->subject_id, 'label' => ['risk' => 'Risiko', 'incident' => 'Insiden'][$l->subject_type] ?? class_basename($l->subject), 'code' => $l->subject->code ?? null, 'name' => $l->subject->name ?? $l->subject->title ?? null] : null]),
            'sources' => self::SOURCES,
            'subject_types' => self::SUBJECT_TYPES,
            'stats' => $this->scoped($user)->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
            'users' => $this->userOptions(),
            'units' => $this->unitOptions(),
            'risks' => $this->riskOptions(),
            'can' => ['write' => $user->can('create', Improvement::class), 'delete' => $user->can('delete', new Improvement())],
        ]);
    }

    /** Pengguna bercakupan unit: unit dalam cakupan, risiko dalam cakupan, ditugaskan kepadanya, atau tanpa unit & risiko. */
    private function scoped($user)
    {
        $ids = $user->accessibleUnitIds();
        return Improvement::query()->when($ids !== null, fn ($q) => $q->where(fn ($w) => $w->whereIn('unit_id', $ids)->orWhereIn('risk_id', \App\Support\UnitScope::riskIdsQuery($user))
            ->orWhere('pic_id', $user->id)->orWhere(fn ($x) => $x->whereNull('unit_id')->whereNull('risk_id'))));
    }

    /** Ringkasan sumber untuk tautan di daftar (kontrol/KRI/insiden/review). */
    private function subjectInfo(Improvement $i): ?array
    {
        $s = $i->subject;
        if (!$s) {
            return null;
        }
        return ['type' => $i->subject_type, 'id' => $s->id, 'code' => $s->code ?? null, 'name' => $s->name ?? $s->title ?? $s->period ?? null, 'risk_id' => $s->risk_id ?? null];
    }

    public function store(Request $request)
    {
        $this->authorize('create', Improvement::class);
        $data = $this->rules($request);
        $i = Improvement::create($data + ['code' => Numbering::next(Improvement::class, 'IMP')]);
        return $this->ok("Tindakan perbaikan {$i->code} ditambahkan.");
    }

    public function update(Request $request, Improvement $improvement)
    {
        $this->authorize('update', $improvement);
        $improvement->update($this->rules($request, $improvement));
        return $this->ok('Tindakan perbaikan diperbarui.');
    }

    public function destroy(Improvement $improvement)
    {
        $this->authorize('delete', $improvement);
        $improvement->delete();
        return $this->ok('Tindakan perbaikan dihapus.');
    }

    public function storeLesson(Request $request)
    {
        $this->authorize('create', Improvement::class);
        $data = $request->validate(['text' => ['required', 'string', 'max:2000']]);
        Lesson::create(['text' => $data['text'], 'created_by' => $request->user()->id]);
        return $this->ok('Lesson learned disimpan.');
    }

    public function destroyLesson(Lesson $lesson)
    {
        $this->authorize('delete', new Improvement(['organization_id' => $lesson->organization_id]));
        $lesson->delete();
        return $this->ok('Lesson learned dihapus.');
    }

    private function rules(Request $request, ?Improvement $current = null): array
    {
        $org = $request->user()->organization_id;
        $data = $request->validate([
            'source_type' => ['required', Rule::in(array_keys(self::SOURCES))],
            'source_ref' => ['nullable', 'string', 'max:120'],
            'subject_type' => ['nullable', Rule::in(array_keys(self::SUBJECT_TYPES))],
            'subject_id' => ['nullable', 'integer', 'required_with:subject_type'],
            'risk_id' => ['nullable', Rule::exists('risks', 'id')->where('organization_id', $org)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'pic_id' => ['nullable', Rule::exists('users', 'id')->where('organization_id', $org)],
            'unit_id' => ['nullable', Rule::exists('org_units', 'id')->where('organization_id', $org)],
            'due_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['open', 'in_progress', 'done'])],
        ]);
        // sumber & risiko yang ditautkan harus terlihat oleh pengguna (cakupan unit); tautan lama boleh dipertahankan
        if (!empty($data['subject_type']) && !($current && $current->subject_type === $data['subject_type'] && (int) $current->subject_id === (int) $data['subject_id'])) {
            $subject = (Relation::getMorphedModel($data['subject_type']))::findOrFail($data['subject_id']);
            abort_unless($request->user()->can('view', $subject), 403);
            $data['risk_id'] ??= $subject->risk_id ?? ($data['subject_type'] === 'control' ? $subject->risks()->value('risks.id') : null);
            $data['source_ref'] ??= $subject->code ?? null;
        }
        if (!empty($data['risk_id']) && (int) $data['risk_id'] !== (int) $current?->risk_id) {
            abort_unless($request->user()->can('view', \App\Models\Risk::findOrFail($data['risk_id'])), 403);
        }
        if (array_key_exists('subject_type', $data) && empty($data['subject_type'])) {
            $data['subject_id'] = null;
        }
        if (!$current && empty($data['unit_id']) && !empty($data['risk_id'])) {
            $data['unit_id'] = \App\Models\Risk::find($data['risk_id'])?->unit_id; // unit default dari risiko terkait
        }
        return $data;
    }

    // ---- Kerangka ISO 31000 ----
    public function framework(Request $request)
    {
        $this->authorize('viewAny', FrameworkItem::class);
        $items = FrameworkItem::orderBy('group')->orderBy('clause')->get();
        $groups = $items->groupBy('group');
        return Inertia::render('Framework/Index', [
            'items' => $items,
            'summary' => ['principle' => $this->pct($groups->get('principle', collect())), 'framework' => $this->pct($groups->get('framework', collect())), 'process' => $this->pct($groups->get('process', collect()))],
            'can' => ['write' => $request->user()->can('update', $items->first() ?? new FrameworkItem())],
        ]);
    }

    public function updateFramework(Request $request, FrameworkItem $item)
    {
        $this->authorize('update', $item);
        $data = $request->validate(['status' => ['required', Rule::in(['met', 'partial', 'unmet'])], 'score' => ['nullable', 'integer', 'between:0,100'], 'note' => ['nullable', 'string', 'max:2000']]);
        $item->update($data);
        return $this->ok("Butir {$item->clause} diperbarui.");
    }

    private function pct($items): array
    {
        $n = $items->count();
        $met = $items->where('status', 'met')->count();
        $partial = $items->where('status', 'partial')->count();
        return ['n' => $n, 'met' => $met, 'partial' => $partial, 'unmet' => $n - $met - $partial, 'pct' => $n ? (int) round(($met + $partial * 0.5) / $n * 100) : 0, 'avg_score' => (int) round((float) $items->avg('score'))];
    }
}

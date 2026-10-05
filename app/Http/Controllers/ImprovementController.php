<?php

namespace App\Http\Controllers;

use App\Models\FrameworkItem;
use App\Models\Improvement;
use App\Models\Lesson;
use App\Support\Numbering;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Continual improvement, lesson learned, dan kepatuhan kerangka ISO 31000 (§4.20, §4.21). */
class ImprovementController extends Controller
{
    public const SOURCES = ['incident' => 'Insiden', 'audit' => 'Temuan Audit', 'control_failure' => 'Kegagalan Kontrol', 'kri_breach' => 'Pelanggaran KRI', 'treatment' => 'Evaluasi Treatment', 'trend' => 'Tren Risiko', 'lesson' => 'Lesson Learned', 'review' => 'Hasil Review', 'other' => 'Lainnya'];

    public function index(Request $request)
    {
        $this->authorize('viewAny', Improvement::class);
        $items = Improvement::with(['pic:id,name', 'unit:id,name'])->orderByRaw("case status when 'open' then 0 when 'in_progress' then 1 else 2 end")->orderBy('due_date')->get();
        return Inertia::render('Improvements/Index', [
            'items' => $items,
            'lessons' => Lesson::with(['creator:id,name', 'subject'])->latest()->limit(100)->get()->map(fn ($l) => ['id' => $l->id, 'text' => $l->text, 'created_at' => $l->created_at, 'creator' => $l->creator?->name,
                'subject' => $l->subject ? ['type' => class_basename($l->subject), 'code' => $l->subject->code ?? null, 'name' => $l->subject->name ?? $l->subject->title ?? null] : null]),
            'sources' => self::SOURCES,
            'stats' => $items->countBy('status'),
            'users' => $this->userOptions(),
            'units' => $this->unitOptions(),
            'can' => ['write' => $request->user()->can('create', Improvement::class), 'delete' => $request->user()->can('delete', new Improvement())],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Improvement::class);
        $i = Improvement::create($this->rules($request) + ['code' => Numbering::next(Improvement::class, 'IMP')]);
        return $this->ok("Tindakan perbaikan {$i->code} ditambahkan.");
    }

    public function update(Request $request, Improvement $improvement)
    {
        $this->authorize('update', $improvement);
        $improvement->update($this->rules($request));
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

    private function rules(Request $request): array
    {
        $org = $request->user()->organization_id;
        return $request->validate([
            'source_type' => ['required', Rule::in(array_keys(self::SOURCES))],
            'source_ref' => ['nullable', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'pic_id' => ['nullable', Rule::exists('users', 'id')->where('organization_id', $org)],
            'unit_id' => ['nullable', Rule::exists('org_units', 'id')->where('organization_id', $org)],
            'due_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['open', 'in_progress', 'done'])],
        ]);
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

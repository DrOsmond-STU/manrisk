<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Manajemen dokumen & bukti (§4.13, §16.3): penyimpanan privat di luar webroot,
 * validasi MIME berdasarkan isi, nama acak, hash SHA-256, unduh lewat gate otorisasi.
 */
class DocumentController extends Controller
{
    public const SUBJECTS = ['risk' => \App\Models\Risk::class, 'control' => \App\Models\Control::class, 'action_plan' => \App\Models\ActionPlan::class, 'incident' => \App\Models\Incident::class, 'review' => \App\Models\Review::class, 'improvement' => \App\Models\Improvement::class];

    public const SUBJECT_LABELS = ['risk' => 'Risiko', 'control' => 'Kontrol', 'action_plan' => 'Action plan', 'incident' => 'Insiden', 'review' => 'Review', 'improvement' => 'Perbaikan'];

    public function index(Request $request)
    {
        $this->authorize('viewAny', Document::class);
        $f = $request->validate(['history' => ['nullable', 'integer'], 'q' => ['nullable', 'string', 'max:100'], 'type' => ['nullable', Rule::in(array_keys(config('manrisk.document_types')))], 'status' => ['nullable', Rule::in(['draft', 'review', 'approved', 'expired'])],
            'subject_kind' => ['nullable', Rule::in(array_keys(self::SUBJECTS))], 'subject_id' => ['nullable', 'integer', 'required_with:subject_kind'], 'upload' => ['nullable', 'boolean']]);
        $q = \App\Support\UnitScope::morph(Document::with(['uploader:id,name', 'subject']), $request->user());
        if (!empty($f['q'])) {
            $q->where(fn ($w) => $w->where('title', 'like', "%{$f['q']}%")->orWhere('original_name', 'like', "%{$f['q']}%"));
        }
        if (!empty($f['history'])) {
            $ids = $this->chain((int) $f['history']);
            $q->whereIn('id', $ids)->reorder()->orderByDesc('version');
        }
        foreach (['type', 'status'] as $k) {
            if (!empty($f[$k])) {
                $q->where($k, $f[$k]);
            }
        }
        // ?subject_kind=&subject_id= → dokumen milik subjek tersebut (dan isian awal form unggah)
        $subject = null;
        if (!empty($f['subject_kind'])) {
            $subject = (self::SUBJECTS[$f['subject_kind']])::find($f['subject_id']);
            $subject = $subject && $request->user()->can('view', $subject) ? $subject : null;
            $q->where('subject_type', $f['subject_kind'])->where('subject_id', $subject?->getKey() ?? 0);
        }
        $docs = (empty($f['history']) ? $q->latest() : $q)->paginate(25)->withQueryString()->through(fn ($d) => $d->only('id', 'type', 'title', 'version', 'original_name', 'mime', 'size', 'status', 'expires_at', 'created_at', 'replaces_id', 'hash', 'subject_type', 'subject_id') + [
            'uploader' => $d->uploader?->name, 'subject' => $d->subject ? $this->subjectInfo($d->subject_type, $d->subject) : null,
            'can_delete' => $request->user()->can('delete', $d),
        ]);
        return Inertia::render('Documents/Index', [
            'documents' => $docs, 'filters' => $f,
            'subject' => $subject ? $this->subjectInfo($f['subject_kind'], $subject) : null,
            'stats' => $this->stats($request),
            'subject_labels' => self::SUBJECT_LABELS,
            'subject_options' => $request->user()->can('create', Document::class) ? $this->subjectOptions($request) : [],
            'can' => ['write' => $request->user()->can('create', Document::class)],
        ]);
    }

    /** Label & tautan subjek dokumen (alias morph, kode, nama). */
    private function subjectInfo(string $type, $s): array
    {
        return ['type' => $type, 'id' => $s->getKey(), 'label' => self::SUBJECT_LABELS[$type] ?? $type, 'code' => $s->code ?? ($type === 'review' ? $s->period : null),
            'name' => $s->name ?? $s->title ?? null, 'risk_id' => $s->risk_id ?? null, 'subject_type' => $type === 'improvement' ? $s->subject_type : null, 'subject_id' => $type === 'improvement' ? $s->subject_id : null];
    }

    /** Pilihan subjek per jenis untuk form unggah, dibatasi cakupan unit pengguna. */
    private function subjectOptions(Request $request): array
    {
        $user = $request->user();
        $ids = $user->accessibleUnitIds();
        $risks = fn () => \App\Support\UnitScope::riskIdsQuery($user);
        $opt = fn ($rows, $label = 'name') => $rows->map(fn ($r) => ['id' => $r->id, 'name' => trim("{$r->code} · {$r->$label}")])->values()->all();
        return [
            'risk' => collect($this->riskOptions())->map(fn ($r) => ['id' => $r->id, 'name' => "{$r->code} · {$r->name}"])->all(),
            'control' => $opt(\App\Models\Control::query()->when($ids !== null, fn ($q) => $q->where(fn ($w) => $w->whereNull('unit_id')->orWhereIn('unit_id', $ids)->orWhereHas('risks', fn ($r) => $r->whereIn('unit_id', $ids))))->orderBy('code')->get(['id', 'code', 'name'])),
            'action_plan' => $opt(\App\Models\ActionPlan::query()->whereNull('cancelled_at')->when($ids !== null, fn ($q) => $q->where(fn ($w) => $w->whereIn('unit_id', $ids)->orWhereIn('risk_id', $risks())->orWhere('pic_id', $user->id)))->orderBy('code')->get(['id', 'code', 'title']), 'title'),
            'incident' => $opt($this->scopeUnits(\App\Models\Incident::query())->orderByDesc('occurred_at')->get(['id', 'code', 'title']), 'title'),
            'improvement' => $opt(\App\Models\Improvement::query()->when($ids !== null, fn ($q) => $q->where(fn ($w) => $w->whereIn('unit_id', $ids)->orWhereIn('risk_id', $risks())->orWhere('pic_id', $user->id)->orWhere(fn ($x) => $x->whereNull('unit_id')->whereNull('risk_id'))))->orderBy('code')->get(['id', 'code', 'title']), 'title'),
        ];
    }

    public function store(Request $request)
    {
        $this->authorize('create', Document::class);
        $data = $request->validate([
            'file' => \App\Support\DocumentStore::rules(),
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(config('manrisk.document_types')))],
            'subject_kind' => ['nullable', Rule::in(array_keys(self::SUBJECTS))],
            'subject_id' => ['nullable', 'integer', 'required_with:subject_kind'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'status' => ['nullable', Rule::in(['draft', 'review', 'approved'])],
            'replaces_id' => ['nullable', Rule::exists('documents', 'id')->where('organization_id', $request->user()->organization_id)],
        ]);
        $subject = null;
        $old = !empty($data['replaces_id']) ? Document::findOrFail($data['replaces_id']) : null;
        if ($old) {
            $this->authorize('view', $old);
            $subject = $old->subject;
        } elseif (!empty($data['subject_kind'])) {
            $subject = (self::SUBJECTS[$data['subject_kind']])::findOrFail($data['subject_id']);
            abort_unless($request->user()->can('view', $subject), 403);
        }
        $version = $old ? Document::whereIn('id', $this->chain($old->id))->max('version') + 1 : 1;
        $doc = \App\Support\DocumentStore::store($data['file'], $request->user(), $subject, [
            'type' => $data['type'], 'title' => $data['title'], 'version' => $version, 'expires_at' => $data['expires_at'] ?? null,
            'status' => $data['status'] ?? 'draft', 'replaces_id' => $data['replaces_id'] ?? null,
        ]);
        return back()->with('success', "Dokumen “{$doc->title}” diunggah.");
    }

    public function download(Request $request, Document $document)
    {
        $this->authorize('view', $document);
        if ($document->subject && !$request->user()->can('view', $document->subject)) {
            abort(403);
        }
        abort_unless(Storage::disk('local')->exists($document->path), 404);
        \App\Models\AuditLog::record('downloaded', $document, [], 'documents.download');
        return Storage::disk('local')->download($document->path, $document->original_name, ['Content-Type' => $document->mime, 'X-Content-Type-Options' => 'nosniff', 'Content-Disposition' => 'attachment']);
    }

    public function update(Request $request, Document $document)
    {
        $this->authorize('update', $document);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'type' => ['required', Rule::in(array_keys(config('manrisk.document_types')))], 'status' => ['required', Rule::in(['draft', 'review', 'approved', 'expired'])], 'expires_at' => ['nullable', 'date']]);
        $document->update($data);
        return $this->ok('Dokumen diperbarui.');
    }

    public function destroy(Document $document)
    {
        $this->authorize('delete', $document);
        $document->delete(); // soft delete; berkas dipertahankan untuk jejak audit
        return $this->ok('Dokumen dihapus.');
    }

    private function stats(Request $request): array
    {
        $base = fn () => \App\Support\UnitScope::morph(Document::query(), $request->user());
        return ['total' => $base()->count(), 'expiring' => $base()->whereNotNull('expires_at')->whereBetween('expires_at', [now(), now()->addDays(60)])->count(),
            'expired' => $base()->where('status', 'expired')->count(), 'by_type' => $base()->selectRaw('type, count(*) n')->groupBy('type')->pluck('n', 'type')];
    }

    /** Semua id dalam rantai versi dokumen (maju & mundur). */
    private function chain(int $id): array
    {
        $ids = [$id];
        $cur = Document::find($id);
        for ($i = 0; $cur && $cur->replaces_id && $i < 50; $i++) {
            $ids[] = $cur->replaces_id;
            $cur = Document::find($cur->replaces_id);
        }
        $frontier = $ids;
        for ($i = 0; $frontier && $i < 50; $i++) {
            $frontier = Document::whereIn('replaces_id', $frontier)->whereNotIn('id', $ids)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }
        return array_values(array_unique($ids));
    }
}

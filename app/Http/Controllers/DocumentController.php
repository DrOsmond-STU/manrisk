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

    public function index(Request $request)
    {
        $this->authorize('viewAny', Document::class);
        $f = $request->validate(['history' => ['nullable', 'integer'], 'q' => ['nullable', 'string', 'max:100'], 'type' => ['nullable', Rule::in(array_keys(config('manrisk.document_types')))], 'status' => ['nullable', Rule::in(['draft', 'review', 'approved', 'expired'])]]);
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
        $docs = (empty($f['history']) ? $q->latest() : $q)->paginate(25)->withQueryString()->through(fn ($d) => $d->only('id', 'type', 'title', 'version', 'original_name', 'mime', 'size', 'status', 'expires_at', 'created_at', 'replaces_id', 'hash') + [
            'uploader' => $d->uploader?->name, 'subject' => $d->subject ? ['type' => class_basename($d->subject), 'code' => $d->subject->code ?? null, 'name' => $d->subject->name ?? $d->subject->title ?? null] : null,
            'can_delete' => $request->user()->can('delete', $d),
        ]);
        return Inertia::render('Documents/Index', [
            'documents' => $docs, 'filters' => $f,
            'stats' => $this->stats($request),
            'risks' => $this->riskOptions(),
            'can' => ['write' => $request->user()->can('create', Document::class)],
        ]);
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

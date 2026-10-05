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
        $f = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'type' => ['nullable', Rule::in(array_keys(config('manrisk.document_types')))], 'status' => ['nullable', Rule::in(['draft', 'review', 'approved', 'expired'])]]);
        $q = Document::with(['uploader:id,name', 'subject']);
        if (!empty($f['q'])) {
            $q->where(fn ($w) => $w->where('title', 'like', "%{$f['q']}%")->orWhere('original_name', 'like', "%{$f['q']}%"));
        }
        foreach (['type', 'status'] as $k) {
            if (!empty($f[$k])) {
                $q->where($k, $f[$k]);
            }
        }
        $docs = $q->latest()->paginate(25)->withQueryString()->through(fn ($d) => $d->only('id', 'type', 'title', 'version', 'original_name', 'mime', 'size', 'status', 'expires_at', 'created_at') + [
            'uploader' => $d->uploader?->name, 'subject' => $d->subject ? ['type' => class_basename($d->subject), 'code' => $d->subject->code ?? null, 'name' => $d->subject->name ?? $d->subject->title ?? null] : null,
            'can_delete' => $request->user()->can('delete', $d),
        ]);
        return Inertia::render('Documents/Index', [
            'documents' => $docs, 'filters' => $f,
            'stats' => ['total' => Document::count(), 'expiring' => Document::whereNotNull('expires_at')->whereBetween('expires_at', [now(), now()->addDays(60)])->count(), 'expired' => Document::where('status', 'expired')->count(), 'by_type' => Document::selectRaw('type, count(*) n')->groupBy('type')->pluck('n', 'type')],
            'risks' => $this->riskOptions(),
            'can' => ['write' => $request->user()->can('create', Document::class)],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Document::class);
        $mimes = implode(',', config('manrisk.upload_mimes'));
        $exts = implode(',', config('manrisk.upload_extensions'));
        $data = $request->validate([
            'file' => ['required', 'file', 'max:' . config('manrisk.upload_max_kb'), "mimetypes:$mimes", "extensions:$exts"],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(config('manrisk.document_types')))],
            'subject_kind' => ['nullable', Rule::in(array_keys(self::SUBJECTS))],
            'subject_id' => ['nullable', 'integer', 'required_with:subject_kind'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'status' => ['nullable', Rule::in(['draft', 'review', 'approved'])],
            'replaces_id' => ['nullable', Rule::exists('documents', 'id')->where('organization_id', $request->user()->organization_id)],
        ]);
        $subject = null;
        if (!empty($data['subject_kind'])) {
            $subject = (self::SUBJECTS[$data['subject_kind']])::findOrFail($data['subject_id']);
            abort_unless($request->user()->can('view', $subject), 403);
        }
        $file = $data['file'];
        // Pertahanan berlapis: periksa tipe berdasarkan isi berkas (magic bytes), bukan hanya ekstensi/klaim klien
        $detected = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        $allowed = array_merge(config('manrisk.upload_mimes'), ['application/zip', 'application/x-ole-storage', 'application/CDFV2']);
        if (!in_array($detected, $allowed, true)) {
            return back()->withErrors(['file' => 'Isi berkas tidak sesuai dengan tipe yang diizinkan (' . $detected . ').']);
        }
        $ext = strtolower($file->getClientOriginalExtension());
        $name = Str::uuid() . '.' . $ext;
        $dir = 'documents/' . $request->user()->organization_id . '/' . now()->format('Y/m');
        $path = $file->storeAs($dir, $name, 'local');
        $version = 1;
        if (!empty($data['replaces_id'])) {
            $old = Document::find($data['replaces_id']);
            $version = ($old?->version ?? 0) + 1;
        }
        $doc = Document::create([
            'subject_type' => $subject?->getMorphClass(), 'subject_id' => $subject?->getKey(),
            'type' => $data['type'], 'title' => $data['title'], 'version' => $version, 'path' => $path,
            'original_name' => mb_substr(preg_replace('/[^\w .()\-]/u', '_', $file->getClientOriginalName()), 0, 255),
            'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'hash' => hash_file('sha256', $file->getRealPath()),
            'uploaded_by' => $request->user()->id, 'expires_at' => $data['expires_at'] ?? null, 'status' => $data['status'] ?? 'draft', 'replaces_id' => $data['replaces_id'] ?? null,
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
}

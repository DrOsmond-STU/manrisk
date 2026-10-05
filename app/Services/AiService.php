<?php

namespace App\Services;

use App\Models\AiInteraction;
use App\Models\Risk;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * AI Assistant (§4.22, §14): adapter penyedia model. Tanpa kunci API, memakai mode
 * heuristik lokal agar fitur tetap dapat diuji. Data yang dikirim dibatasi pada
 * ringkasan risiko (tanpa data pribadi) dan setiap pemanggilan dicatat.
 */
class AiService
{
    public function available(): bool
    {
        return (bool) config('manrisk.ai.enabled');
    }

    public function hasProvider(): bool
    {
        return (bool) config('manrisk.ai.api_key');
    }

    public function remaining(User $user): int
    {
        $used = AiInteraction::withoutGlobalScopes()->where('user_id', $user->id)->where('created_at', '>=', now()->startOfDay())->count();
        return max(0, (int) config('manrisk.ai.daily_limit') - $used);
    }

    public const STRUCTURED = ['identify', 'statement'];

    /** Paragraf ringkasan dashboard (F-DSH-09), di-cache 15 menit; tidak memotong kuota pengguna. */
    public function dashboardSummary(array $s, array $top, string $cacheKey): string
    {
        return \Illuminate\Support\Facades\Cache::remember('ai-dash:' . $cacheKey . ':' . md5(json_encode([$s, $top])), now()->addMinutes(15), function () use ($s, $top) {
            $local = $this->localSummary($s, $top);
            if (!$this->available() || !$this->hasProvider()) {
                return $local;
            }
            try {
                [$text] = $this->callAnthropic($this->prompt('summarize', ['summary' => $s, 'top' => $top]) . "\n\nBalas dalam SATU paragraf (maks. 90 kata), sebut ID risiko.");
                return trim($text) ?: $local;
            } catch (\Throwable $e) {
                report($e);
                return $local;
            }
        });
    }

    private function localSummary(array $s, array $top): string
    {
        $t = collect($top)->take(3)->map(fn ($r) => "{$r['code']} ({$r['residual_score']})")->implode(', ');
        $dir = ($s['up'] ?? 0) > ($s['down'] ?? 0) ? 'cenderung meningkat' : (($s['down'] ?? 0) > ($s['up'] ?? 0) ? 'cenderung menurun' : 'relatif stabil');
        return "Terdapat {$s['total']} risiko aktif dengan {$s['high']} berlevel tinggi/sangat tinggi; profil risiko {$dir} dibanding periode lalu ({$s['up']} naik, {$s['down']} turun). "
            . ($t ? "Risiko prioritas: {$t}. " : '')
            . "Realisasi mitigasi rata-rata {$s['realization']}% dengan {$s['overdue']} action plan terlambat; {$s['kri_breach']} KRI melewati ambang. "
            . (($s['escalate'] ?? 0) > 0 ? "Sebanyak {$s['escalate']} risiko memerlukan keputusan manajemen." : 'Tidak ada risiko yang melewati ambang eskalasi.');
    }

    /**
     * Keluaran terstruktur untuk mengisi formulir (spesifikasi §14.2).
     * identify → {candidates:[{name,category,source_kind,cause,event,impact,likelihood,impact_score}]}
     * statement → {cause,event,impact,name}
     */
    public function structured(User $user, string $feature, array $input): array
    {
        $res = $this->run($user, $feature, $input);
        $json = null;
        if (preg_match('/\{.*\}/s', $res['text'], $m)) {
            $json = json_decode($m[0], true);
        }
        if (!is_array($json)) {
            $json = json_decode($this->heuristic($feature, $input), true) ?: [];
        }
        return ['data' => $json, 'model' => $res['model'], 'remaining' => $res['remaining']];
    }

    /** Fitur: suggest_risk | suggest_controls | suggest_treatment | summarize | explain_score | draft_report | identify | statement */
    public function run(User $user, string $feature, array $input): array
    {
        if (!$this->available()) {
            throw ValidationException::withMessages(['ai' => 'Fitur AI dinonaktifkan.']);
        }
        if ($this->remaining($user) <= 0) {
            throw ValidationException::withMessages(['ai' => 'Kuota AI harian Anda habis.']);
        }
        $prompt = $this->prompt($feature, $input);
        $ok = true;
        $model = null;
        $tokens = [0, 0];
        try {
            if ($this->hasProvider()) {
                [$text, $tokens, $model] = $this->callAnthropic($prompt);
            } else {
                $text = $this->heuristic($feature, $input);
                $model = 'local-heuristic';
            }
        } catch (\Throwable $e) {
            $ok = false;
            report($e);
            $text = 'Layanan AI sedang tidak tersedia. ' . $this->heuristic($feature, $input);
            $model = 'fallback';
        }
        AiInteraction::create(['organization_id' => $user->organization_id, 'user_id' => $user->id, 'feature' => $feature, 'prompt_hash' => hash('sha256', $prompt), 'tokens_in' => $tokens[0], 'tokens_out' => $tokens[1], 'model' => $model, 'ok' => $ok, 'created_at' => now()]);
        return ['text' => $text, 'model' => $model, 'remaining' => $this->remaining($user)];
    }

    private function prompt(string $feature, array $input): string
    {
        $ctx = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $task = match ($feature) {
            'suggest_risk' => 'Berdasarkan konteks berikut, usulkan 3–5 risiko dengan struktur Penyebab → Peristiwa → Dampak, kategori, sumber, dan perkiraan kemungkinan/dampak (1–5).',
            'suggest_controls' => 'Usulkan kontrol yang relevan (preventif/detektif/korektif) untuk risiko berikut beserta frekuensi dan indikator efektivitasnya.',
            'suggest_treatment' => 'Rekomendasikan opsi treatment (hindari/kurangi/bagikan/terima) dan 3 action plan konkret dengan PIC generik, tenggat, dan perkiraan penurunan skor.',
            'explain_score' => 'Jelaskan skor inheren/residual/target risiko berikut, status evaluasi terhadap appetite/tolerance, dan apa artinya bagi manajemen dalam bahasa sederhana.',
            'summarize' => 'Buat ringkasan eksekutif profil risiko berikut dalam 5 poin, sebutkan risiko tertinggi, tren, dan rekomendasi prioritas.',
            'identify' => 'Identifikasi 5 risiko dari konteks/proses berikut. Balas HANYA JSON: {"candidates":[{"name":"","category":"","source_kind":"people|process|technology|infrastructure|regulation|financial|third_party","cause":"","event":"","impact":"","likelihood":1-5,"impact_score":1-5}]}. Kategori pilih dari: ' . implode(', ', $input['categories'] ?? []) . '.',
            'statement' => 'Ubah catatan bebas berikut menjadi pernyataan risiko. Balas HANYA JSON: {"name":"","cause":"","event":"","impact":""}.',
            'draft_report' => 'Susun naskah laporan manajemen risiko (pendahuluan, profil risiko, risiko utama, status mitigasi, KRI & insiden, rekomendasi) berdasarkan data berikut. Gunakan heading markdown.',
            default => 'Bantu analisis manajemen risiko berikut.',
        };
        return "Anda adalah asisten manajemen risiko ISO 31000:2018 untuk organisasi di Indonesia. Jawab ringkas, terstruktur, dalam Bahasa Indonesia.\n\nTugas: $task\n\nData:\n$ctx";
    }

    private function callAnthropic(string $prompt): array
    {
        $res = Http::withHeaders(['x-api-key' => config('manrisk.ai.api_key'), 'anthropic-version' => '2023-06-01'])
            ->timeout((int) config('manrisk.ai.timeout'))
            ->post('https://api.anthropic.com/v1/messages', ['model' => config('manrisk.ai.model'), 'max_tokens' => 1500, 'messages' => [['role' => 'user', 'content' => $prompt]]])
            ->throw()->json();
        $text = collect($res['content'] ?? [])->where('type', 'text')->pluck('text')->implode("\n");
        return [$text, [(int) ($res['usage']['input_tokens'] ?? 0), (int) ($res['usage']['output_tokens'] ?? 0)], $res['model'] ?? config('manrisk.ai.model')];
    }

    /** Mode tanpa penyedia: jawaban berbasis aturan dari data yang ada. */
    private function heuristic(string $feature, array $in): string
    {
        $treat = config('manrisk.treatments');
        switch ($feature) {
            case 'identify':
                return json_encode(['candidates' => $this->heuristicCandidates((string) ($in['context'] ?? ''), $in['categories'] ?? [])], JSON_UNESCAPED_UNICODE);
            case 'statement':
                return json_encode($this->heuristicStatement((string) ($in['context'] ?? '')), JSON_UNESCAPED_UNICODE);
            case 'explain_score':
                $r = $in['risk'] ?? [];
                $ev = \App\Support\Scoring::EVALUATIONS[$r['evaluation'] ?? 'monitor'] ?? '';
                return "**Penjelasan skor {$r['code']}**\n\n- Skor inheren {$r['inherent_score']} (L{$r['inherent_l']}×I{$r['inherent_i']}) adalah paparan sebelum kontrol.\n- Skor residual {$r['residual_score']} (L{$r['residual_l']}×I{$r['residual_i']}) setelah kontrol yang ada, berada pada level **" . \App\Support\Scoring::levelLabel($r['residual_level'] ?? 'medium') . "**.\n- Dibandingkan appetite {$r['appetite']} dan tolerance {$r['tolerance']} kategori, status evaluasinya **$ev**.\n- Target {$r['target_score']} dicapai bila action plan mengurangi kemungkinan/dampak sesuai rencana.\n\nRekomendasi: " . (($r['residual_score'] ?? 0) > ($r['tolerance'] ?? 9) ? 'segera lengkapi action plan dan tetapkan KRI pemantau.' : 'pertahankan kontrol dan pantau KRI secara berkala.');
            case 'suggest_controls':
                $r = $in['risk'] ?? [];
                return "**Usulan kontrol untuk {$r['name']}**\n\n1. *Preventif* — SOP dan checklist untuk mencegah penyebab “" . mb_substr($r['cause'] ?? '', 0, 80) . "” (bulanan).\n2. *Detektif* — Pemantauan indikator dan rekonsiliasi berkala untuk mendeteksi “" . mb_substr($r['event'] ?? '', 0, 80) . "” lebih dini (mingguan).\n3. *Korektif* — Rencana tanggap dan eskalasi bila dampak “" . mb_substr($r['impact'] ?? '', 0, 80) . "” terjadi.\n\nUkur efektivitas melalui pengujian desain & operasi setiap kuartal.";
            case 'suggest_treatment':
                $r = $in['risk'] ?? [];
                $opt = ($r['residual_score'] ?? 0) >= 16 ? 'avoid' : (($r['residual_score'] ?? 0) >= 10 ? 'reduce' : 'retain');
                return "**Rekomendasi treatment: {$treat[$opt]}**\n\n1. Perkuat kontrol kunci yang ada dan tetapkan PIC (30 hari) — perkiraan ΔL −1.\n2. Tambahkan kontrol detektif/KRI dengan ambang peringatan (60 hari) — ΔI −1.\n3. Latihan/sosialisasi kepada pelaksana proses (90 hari).\n\nSetelah seluruh rencana selesai, skor diproyeksikan turun ke target {$r['target_score']}.";
            case 'suggest_risk':
                $ctx = $in['context'] ?? '';
                return "**Usulan risiko dari konteks “" . mb_substr($ctx, 0, 60) . "”**\n\n1. Penyebab: keterbatasan SDM/kompetensi → Peristiwa: keterlambatan pelaksanaan → Dampak: target kinerja tidak tercapai (Operasional, L3 I3).\n2. Penyebab: ketergantungan sistem → Peristiwa: gangguan layanan → Dampak: operasional terhenti (TI, L2 I4).\n3. Penyebab: perubahan regulasi → Peristiwa: ketidaksesuaian prosedur → Dampak: sanksi/temuan (Kepatuhan, L2 I3).\n4. Penyebab: pengendalian anggaran lemah → Peristiwa: realisasi menyimpang → Dampak: kerugian finansial (Keuangan, L3 I3).";
            case 'summarize':
            case 'draft_report':
                $s = $in['summary'] ?? [];
                $top = collect($in['top'] ?? [])->take(5)->map(fn ($r, $i) => ($i + 1) . ". {$r['code']} {$r['name']} — skor {$r['residual_score']} (" . \App\Support\Scoring::levelLabel($r['residual_level']) . ')')->implode("\n");
                $head = $feature === 'draft_report' ? "# Laporan Manajemen Risiko\n\n## 1. Pendahuluan\nLaporan ini menyajikan profil risiko organisasi berdasarkan Risk Register per " . now()->translatedFormat('d F Y') . ".\n\n## 2. Profil Risiko\n" : "**Ringkasan eksekutif**\n\n";
                return $head . "- Total {$s['total']} risiko aktif; {$s['high']} berlevel tinggi/sangat tinggi; rata-rata skor residual {$s['avg']}.\n- {$s['escalate']} risiko melewati ambang eskalasi dan memerlukan perhatian manajemen.\n- Penyelesaian action plan: {$s['plan_done']}/{$s['plan_total']}.\n- Insiden tahun berjalan: {$s['incidents_ytd']} dengan kerugian Rp " . number_format((float) ($s['loss_ytd'] ?? 0), 0, ',', '.') . ".\n\n**Risiko utama**\n$top\n\n**Rekomendasi:** prioritaskan mitigasi risiko sangat tinggi, tinjau kontrol yang lemah, dan tindak lanjuti KRI yang melampaui ambang.";
        }
        return 'Tidak ada saran.';
    }

    /** Kandidat risiko berbasis aturan (mode tanpa penyedia AI). */
    private function heuristicCandidates(string $ctx, array $categories): array
    {
        $topic = trim(mb_substr(preg_replace('/\s+/', ' ', $ctx), 0, 80)) ?: 'proses bisnis';
        $pick = function (array $prefs) use ($categories) {
            foreach ($prefs as $p) {
                foreach ($categories as $c) {
                    if (mb_stripos($c, $p) !== false) {
                        return $c;
                    }
                }
            }
            return $categories[0] ?? 'Operasional';
        };
        return [
            ['name' => "Keterlambatan pelaksanaan {$topic}", 'category' => $pick(['Operasional']), 'source_kind' => 'people', 'cause' => "keterbatasan SDM dan kompetensi pada {$topic}", 'event' => "pelaksanaan {$topic} terlambat dari jadwal", 'impact' => 'target kinerja dan layanan tidak tercapai', 'likelihood' => 3, 'impact_score' => 3],
            ['name' => "Gangguan sistem pendukung {$topic}", 'category' => $pick(['Teknologi', 'TI', 'Operasional']), 'source_kind' => 'technology', 'cause' => "ketergantungan pada aplikasi tunggal tanpa cadangan untuk {$topic}", 'event' => 'sistem pendukung tidak tersedia', 'impact' => 'proses terhenti dan data tidak dapat diakses', 'likelihood' => 2, 'impact_score' => 4],
            ['name' => "Ketidaksesuaian {$topic} dengan regulasi", 'category' => $pick(['Kepatuhan', 'Hukum']), 'source_kind' => 'regulation', 'cause' => 'perubahan regulasi belum diikuti pembaruan prosedur', 'event' => "{$topic} tidak sesuai ketentuan", 'impact' => 'temuan audit atau sanksi', 'likelihood' => 2, 'impact_score' => 3],
            ['name' => "Penyimpangan anggaran {$topic}", 'category' => $pick(['Keuangan']), 'source_kind' => 'financial', 'cause' => 'pengendalian realisasi anggaran lemah', 'event' => 'realisasi anggaran menyimpang dari rencana', 'impact' => 'kerugian finansial dan temuan pemeriksa', 'likelihood' => 3, 'impact_score' => 3],
            ['name' => "Kegagalan pihak ketiga pada {$topic}", 'category' => $pick(['Operasional', 'Strategis']), 'source_kind' => 'third_party', 'cause' => 'ketergantungan pada satu penyedia tanpa SLA memadai', 'event' => 'penyedia gagal memenuhi kewajiban', 'impact' => 'layanan tertunda dan biaya tambahan', 'likelihood' => 2, 'impact_score' => 3],
        ];
    }

    /** Pemecahan catatan bebas menjadi penyebab → peristiwa → dampak (mode tanpa penyedia AI). */
    private function heuristicStatement(string $text): array
    {
        $t = trim(preg_replace('/\s+/', ' ', $text));
        $cause = $event = $impact = '';
        if (preg_match('/karena\s+(.+?)(?:,|\s+(?:sehingga|maka|mengakibatkan|menyebabkan|dapat terjadi|terjadi))/iu', $t, $m)) {
            $cause = $m[1];
        }
        if (preg_match('/(?:dapat terjadi|mungkin terjadi|terjadi)\s+(.+?)(?:,|\s+(?:sehingga|yang berdampak|mengakibatkan|menyebabkan)|$)/iu', $t, $m)) {
            $event = $m[1];
        }
        if (preg_match('/(?:sehingga|berdampak pada|mengakibatkan|menyebabkan)\s+(.+)$/iu', $t, $m)) {
            $impact = rtrim($m[1], '. ');
        }
        $parts = preg_split('/[.;]\s*/', $t);
        $cause = $cause ?: ($parts[0] ?? $t);
        $event = $event ?: ($parts[1] ?? $parts[0] ?? $t);
        $impact = $impact ?: ($parts[2] ?? 'terganggunya pencapaian sasaran');
        return ['name' => ucfirst(mb_substr($event, 0, 120)), 'cause' => $cause, 'event' => $event, 'impact' => $impact];
    }
}

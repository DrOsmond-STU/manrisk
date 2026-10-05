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

    /** Fitur: suggest_risk | suggest_controls | suggest_treatment | summarize | explain_score | draft_report */
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
}

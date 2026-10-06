<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import Field from '../../Components/Field.vue';
import { toast } from '../../lib/format';
const props = defineProps({ enabled: Boolean, provider: String, remaining: Number, risks: Array, categories: Array });
const params = new URLSearchParams(window.location.search);
const feature = ref(params.get('risk') ? 'explain_score' : 'summarize');
const riskId = ref(params.get('risk') || '');
const context = ref('');
const out = ref(''); const busy = ref(false); const left = ref(props.remaining); const model = ref(''); const refs = ref({}); const candidates = ref([]);
const backRisk = params.get('risk') ? props.risks.find((r) => String(r.id) === params.get('risk')) || { id: encodeURIComponent(params.get('risk')), code: '', name: '' } : null;
const features = { identify: 'Identifikasi kandidat risiko (siap dibuat)', summarize: 'Ringkasan eksekutif profil risiko', draft_report: 'Draf naskah laporan manajemen risiko', explain_score: 'Jelaskan skor & evaluasi suatu risiko', suggest_controls: 'Usulkan kontrol untuk suatu risiko', suggest_treatment: 'Rekomendasi treatment & action plan', suggest_risk: 'Usulkan risiko dari konteks/proses' };
const needsRisk = (f) => ['explain_score', 'suggest_controls', 'suggest_treatment'].includes(f);
const needsContext = (f) => ['suggest_risk', 'identify'].includes(f);
// tautan "Buat risiko dari saran ini" → formulir risiko terisi (penyebab/peristiwa/dampak, kategori bila dikenali)
const createUrl = (c) => { const cat = props.categories?.find((k) => k.name.toLowerCase() === String(c.category || '').toLowerCase());
  return '/risks/create?' + new URLSearchParams(Object.fromEntries(Object.entries({ name: c.name, cause: c.cause, event: c.event, impact: c.impact, category_id: cat?.id }).filter(([, v]) => v))); };
// saran teks bebas berformat "Penyebab: … → Peristiwa: … → Dampak: … (Kategori, L3 I3)" diurai menjadi kandidat
const parsed = computed(() => (feature.value === 'suggest_risk' && out.value ? [...out.value.matchAll(/Penyebab:\s*(.+?)\s*→\s*Peristiwa:\s*(.+?)\s*→\s*Dampak:\s*(.+?)(?:\s*\(([^,()]+)[^()]*\))?\.?$/gm)]
  .map((m) => ({ cause: m[1].replace(/\*/g, ''), event: m[2].replace(/\*/g, ''), impact: m[3].replace(/\*/g, ''), category: m[4]?.trim(), name: m[2].replace(/\*/g, '').replace(/^./, (c) => c.toUpperCase()) })) : []));
const suggestions = computed(() => (candidates.value.length ? candidates.value : parsed.value));
// kode risiko (dalam cakupan) → id, untuk menautkan kode yang disebut pada hasil
const codeMap = computed(() => ({ ...Object.fromEntries(props.risks.map((r) => [r.code, r.id])), ...refs.value }));
const run = async () => {
  if (needsRisk(feature.value) && !riskId.value) { toast('Pilih risiko terlebih dahulu.', 'warn'); return; }
  if (feature.value === 'identify' && !context.value.trim()) { toast('Uraikan konteks/proses terlebih dahulu.', 'warn'); return; }
  busy.value = true; out.value = ''; candidates.value = []; refs.value = {};
  try {
    const res = await fetch('/ai', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ feature: feature.value, risk_id: riskId.value || null, context: context.value }) });
    const data = await res.json();
    if (!res.ok) { toast(data.message || Object.values(data.errors || {}).flat()[0] || 'Gagal.', 'bad'); return; }
    left.value = data.remaining; model.value = data.model; refs.value = Array.isArray(data.refs) ? {} : data.refs || {};
    if (data.data) { candidates.value = data.data.candidates || []; out.value = candidates.value.length ? `**${candidates.value.length} kandidat risiko** teridentifikasi. Tinjau lalu buat risiko dari saran yang relevan.` : 'Tidak ada kandidat risiko.'; } else out.value = data.text;
  } catch (e) { toast('Gagal menghubungi layanan AI.', 'bad'); } finally { busy.value = false; }
};
const esc = (s) => s.replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));
const escRe = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const linkCodes = (h) => { const codes = Object.keys(codeMap.value).filter(Boolean).sort((a, b) => b.length - a.length); if (!codes.length) return h;
  return h.replace(new RegExp(`(?<![\\w-])(${codes.map(escRe).join('|')})(?![\\w-])`, 'g'), (c) => `<a href="/risks/${codeMap.value[c]}">${c}</a>`); };
const md = (s) => linkCodes(esc(s).replace(/^### (.*)$/gm, '<h3>$1</h3>').replace(/^## (.*)$/gm, '<h2>$1</h2>').replace(/^# (.*)$/gm, '<h1>$1</h1>').replace(/\*\*(.+?)\*\*/g, '<b>$1</b>').replace(/\*(.+?)\*/g, '<i>$1</i>').replace(/^\d+\. (.*)$/gm, '<li>$1</li>').replace(/^- (.*)$/gm, '<li>$1</li>').replace(/(<li>.*<\/li>\n?)+/g, (m) => `<ul>${m}</ul>`).replace(/\n{2,}/g, '<br><br>').replace(/\n/g, '<br>'));
const copy = () => navigator.clipboard?.writeText(out.value).then(() => toast('Disalin.', 'ok'));
</script>
<template>
  <Head title="AI Risk Assistant" />
  <PageHead kicker="AI" title="AI Risk Assistant" :sub="`Bantuan analisis berbasis data register. Penyedia: ${provider}. Sisa kuota hari ini: ${left}. Hasil AI adalah saran; keputusan tetap pada pemilik risiko.`" />
  <div v-if="backRisk" class="row" style="margin-bottom:10px"><Link :href="`/risks/${backRisk.id}`" class="btn sm ghost c-indigo">← Kembali ke risiko <span class="mono">{{ backRisk.code }}</span> {{ backRisk.name }}</Link></div>
  <div v-if="!enabled" class="alert-box warn"><Icon name="alert" /><span>Fitur AI dinonaktifkan oleh administrator.</span></div>
  <div class="s-grid">
    <div class="card" style="grid-column:span 4"><div class="card-h"><h3>Permintaan</h3></div><div class="card-b stack">
      <Field v-model="feature" type="select" label="Fitur" :options="features" empty="" />
      <Field v-if="needsRisk(feature)" v-model="riskId" type="select" label="Risiko" :options="risks.map((r) => ({ id: r.id, name: `${r.code} · ${r.name}` }))" required />
      <Field v-if="needsContext(feature)" v-model="context" type="textarea" label="Konteks / proses bisnis" :rows="5" placeholder="Uraikan proses, unit, atau perubahan yang ingin dianalisis…" maxlength="3000" />
      <button type="button" class="btn block c-violet" :disabled="busy || !enabled" @click="run"><Icon name="spark" />{{ busy ? 'Menganalisis…' : 'Jalankan' }}</button>
      <p class="hint">Data yang dikirim: ringkasan risiko (kode, nama, pernyataan, skor) tanpa data pribadi. Setiap permintaan dicatat (fitur, waktu, pengguna).</p>
    </div></div>
    <div class="card" style="grid-column:span 8"><div class="card-h"><h3>Hasil</h3><span class="row"><span v-if="model" class="hint">model: {{ model }}</span><button v-if="out" type="button" class="btn sm c-blue" @click="copy"><Icon name="copy" />Salin</button></span></div><div class="card-b stack"><div v-if="out" class="md" v-html="md(out)"></div><div v-else class="empty">Pilih fitur lalu jalankan.</div>
      <div v-if="suggestions.length" class="stack"><h4 style="margin:6px 0 0">Saran risiko</h4>
        <div v-for="(c, i) in suggestions" :key="i" class="card" style="box-shadow:none"><div class="card-b" style="padding:10px 12px">
          <div class="row" style="justify-content:space-between;gap:8px;flex-wrap:wrap"><b>{{ c.name }}</b><span v-if="c.category" class="hint">{{ c.category }}<template v-if="c.likelihood"> · L{{ c.likelihood }} I{{ c.impact_score }}</template></span></div>
          <div class="t-sub">Penyebab: {{ c.cause }} → Peristiwa: {{ c.event }} → Dampak: {{ c.impact }}</div>
          <div class="row" style="justify-content:flex-end"><Link :href="createUrl(c)" class="btn sm c-green"><Icon name="plus" />Buat risiko dari saran ini</Link></div>
        </div></div>
      </div></div></div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import Field from '../../Components/Field.vue';
import { toast } from '../../lib/format';
const props = defineProps({ enabled: Boolean, provider: String, remaining: Number, risks: Array });
const params = new URLSearchParams(window.location.search);
const feature = ref(params.get('risk') ? 'explain_score' : 'summarize');
const riskId = ref(params.get('risk') || '');
const context = ref('');
const out = ref(''); const busy = ref(false); const left = ref(props.remaining); const model = ref('');
const features = { summarize: 'Ringkasan eksekutif profil risiko', draft_report: 'Draf naskah laporan manajemen risiko', explain_score: 'Jelaskan skor & evaluasi suatu risiko', suggest_controls: 'Usulkan kontrol untuk suatu risiko', suggest_treatment: 'Rekomendasi treatment & action plan', suggest_risk: 'Usulkan risiko dari konteks/proses' };
const needsRisk = (f) => ['explain_score', 'suggest_controls', 'suggest_treatment'].includes(f);
const run = async () => {
  if (needsRisk(feature.value) && !riskId.value) { toast('Pilih risiko terlebih dahulu.', 'warn'); return; }
  busy.value = true; out.value = '';
  try {
    const res = await fetch('/ai', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ feature: feature.value, risk_id: riskId.value || null, context: context.value }) });
    const data = await res.json();
    if (!res.ok) { toast(data.message || Object.values(data.errors || {}).flat()[0] || 'Gagal.', 'bad'); return; }
    out.value = data.text; left.value = data.remaining; model.value = data.model;
  } catch (e) { toast('Gagal menghubungi layanan AI.', 'bad'); } finally { busy.value = false; }
};
const esc = (s) => s.replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));
const md = (s) => esc(s).replace(/^### (.*)$/gm, '<h3>$1</h3>').replace(/^## (.*)$/gm, '<h2>$1</h2>').replace(/^# (.*)$/gm, '<h1>$1</h1>').replace(/\*\*(.+?)\*\*/g, '<b>$1</b>').replace(/\*(.+?)\*/g, '<i>$1</i>').replace(/^\d+\. (.*)$/gm, '<li>$1</li>').replace(/^- (.*)$/gm, '<li>$1</li>').replace(/(<li>.*<\/li>\n?)+/g, (m) => `<ul>${m}</ul>`).replace(/\n{2,}/g, '<br><br>').replace(/\n/g, '<br>');
const copy = () => navigator.clipboard?.writeText(out.value).then(() => toast('Disalin.', 'ok'));
</script>
<template>
  <Head title="AI Risk Assistant" />
  <PageHead kicker="AI" title="AI Risk Assistant" :sub="`Bantuan analisis berbasis data register. Penyedia: ${provider}. Sisa kuota hari ini: ${left}. Hasil AI adalah saran; keputusan tetap pada pemilik risiko.`" />
  <div v-if="!enabled" class="alert-box warn"><Icon name="alert" /><span>Fitur AI dinonaktifkan oleh administrator.</span></div>
  <div class="s-grid">
    <div class="card" style="grid-column:span 4"><div class="card-h"><h3>Permintaan</h3></div><div class="card-b stack">
      <Field v-model="feature" type="select" label="Fitur" :options="features" empty="" />
      <Field v-if="needsRisk(feature)" v-model="riskId" type="select" label="Risiko" :options="risks.map((r) => ({ id: r.id, name: `${r.code} · ${r.name}` }))" required />
      <Field v-if="feature === 'suggest_risk'" v-model="context" type="textarea" label="Konteks / proses bisnis" :rows="5" placeholder="Uraikan proses, unit, atau perubahan yang ingin dianalisis…" maxlength="3000" />
      <button type="button" class="btn block c-violet" :disabled="busy || !enabled" @click="run"><Icon name="spark" />{{ busy ? 'Menganalisis…' : 'Jalankan' }}</button>
      <p class="hint">Data yang dikirim: ringkasan risiko (kode, nama, pernyataan, skor) tanpa data pribadi. Setiap permintaan dicatat (fitur, waktu, pengguna).</p>
    </div></div>
    <div class="card" style="grid-column:span 8"><div class="card-h"><h3>Hasil</h3><span class="row"><span v-if="model" class="hint">model: {{ model }}</span><button v-if="out" type="button" class="btn sm c-blue" @click="copy"><Icon name="copy" />Salin</button></span></div><div class="card-b"><div v-if="out" class="md" v-html="md(out)"></div><div v-else class="empty">Pilih fitur lalu jalankan.</div></div></div>
  </div>
</template>

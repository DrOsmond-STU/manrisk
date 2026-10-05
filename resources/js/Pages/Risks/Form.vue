<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import PageHead from '../../Components/PageHead.vue';
import Field from '../../Components/Field.vue';
import Heatmap from '../../Components/Heatmap.vue';
import Pill from '../../Components/Pill.vue';
import { lvFromScore } from '../../lib/format';
const props = defineProps({ risk: Object, units: Array, categories: Array, owners: Array, objectives: Array, processes: Array, controls: Array, criteria: Object });
const L = usePage().props.labels;
const edit = !!props.risk;
const r = props.risk || {};
const form = useForm({
  name: r.name || '', unit_id: r.unit_id || '', objective_id: r.objective_id || '', process_id: r.process_id || '', category_id: r.category_id || '', owner_id: r.owner_id || '',
  cause: r.cause || '', event: r.event || '', impact: r.impact || '', source_type: r.source_type || 'internal', source_kind: r.source_kind || 'process', existing_controls: r.existing_controls || '',
  treatment: r.treatment || 'reduce', treatment_note: r.treatment_note || '', due_date: r.due_date ? String(r.due_date).slice(0, 10) : '',
  inherent_l: r.inherent_l || 3, inherent_i: r.inherent_i || 3, inherent_dims: r.inherent_dims || {}, residual_l: r.residual_l || 2, residual_i: r.residual_i || 3, residual_dims: r.residual_dims || {}, target_l: r.target_l || 1, target_i: r.target_i || 3,
  control_ids: r.control_ids || [], note: '',
});
const step = ref(1);
const steps = ['Identifikasi', 'Analisis & Skor', 'Evaluasi & Treatment', 'Tinjau & Simpan'];
const matrix = props.criteria?.matrix || null;
const dims = props.criteria?.dimensions || [];
const lvOf = (l, i) => (matrix && matrix[`${l}-${i}`]) || lvFromScore(l * i);
const cat = computed(() => props.categories.find((c) => c.id == form.category_id));
const score = (l, i) => Number(l) * Number(i);
const evalOf = (s) => { if (s >= 20) return 'critical'; if (s >= 16) return 'escalate'; if (!cat.value) return 'monitor'; if (s > cat.value.tolerance) return 'treat'; if (s > cat.value.appetite) return 'monitor'; return 'acceptable'; };
const pick = (prefix, key, l, i) => { form[`${prefix}_l`] = l; form[`${prefix}_i`] = i; };
const dimChange = (prefix) => { const vals = Object.values(form[`${prefix}_dims`] || {}).map(Number).filter((v) => v >= 1 && v <= 5); if (vals.length) form[`${prefix}_i`] = Math.max(...vals); };
const statement = computed(() => (form.cause && form.event && form.impact) ? `Karena ${form.cause.trim().replace(/\.$/, '')}, mungkin terjadi ${form.event.trim().replace(/\.$/, '')}, yang berdampak pada ${form.impact.trim().replace(/\.$/, '')}.` : '');
const warn = computed(() => { const w = []; if (score(form.residual_l, form.residual_i) > score(form.inherent_l, form.inherent_i)) w.push('Skor residual tidak boleh melebihi skor inheren.'); if (score(form.target_l, form.target_i) > score(form.residual_l, form.residual_i)) w.push('Skor target tidak boleh melebihi skor residual.'); return w; });
const stepOk = computed(() => step.value === 1 ? (form.name && form.unit_id && form.category_id && form.owner_id && form.cause && form.event && form.impact) : step.value === 2 ? !warn.value.length : true);
const submit = () => { if (edit) form.put(`/risks/${r.id}`); else form.post('/risks'); };
const toggleControl = (id) => { const i = form.control_ids.indexOf(id); if (i >= 0) form.control_ids.splice(i, 1); else form.control_ids.push(id); };
const firstErrorStep = () => { const e = Object.keys(form.errors); if (!e.length) return null; if (e.some((k) => ['name', 'unit_id', 'category_id', 'owner_id', 'cause', 'event', 'impact', 'source_kind', 'objective_id', 'process_id'].includes(k))) return 1; if (e.some((k) => k.includes('_l') || k.includes('_i') || k.includes('dims'))) return 2; return 3; };
</script>
<template>
  <Head :title="edit ? `Ubah ${r.code}` : 'Identifikasi risiko'" />
  <PageHead kicker="Manajemen Risiko" :title="edit ? `Ubah risiko ${r.code}` : 'Identifikasi risiko baru'" sub="Ikuti langkah: identifikasi (penyebab → peristiwa → dampak), analisis skor inheren/residual/target, evaluasi & treatment, lalu simpan sebagai draft untuk diajukan.">
    <Link :href="edit ? `/risks/${r.id}` : '/risks'" class="btn ghost c-indigo">Batal</Link>
  </PageHead>
  <div class="steps"><button v-for="(s, i) in steps" :key="s" type="button" class="st" :class="{ done: step > i + 1, cur: step === i + 1, bad: firstErrorStep() === i + 1 }" style="border:0;text-align:left;font:inherit;cursor:pointer" @click="step = i + 1"><b>{{ i + 1 }}.</b> {{ s }}</button></div>
  <div v-if="Object.keys(form.errors).length" class="alert-box bad"><Icon name="alert" /><span>Periksa isian yang ditandai merah: {{ Object.values(form.errors)[0] }}</span></div>
  <form @submit.prevent="submit">
    <div v-show="step === 1" class="card"><div class="card-h"><h3>1. Identifikasi risiko</h3><span class="sub">ISO 31000 §6.4.2</span></div><div class="card-b form-grid">
      <Field v-model="form.name" label="Nama risiko" required span :error="form.errors.name" maxlength="255" placeholder="mis. Gangguan layanan pusat data" />
      <Field v-model="form.unit_id" type="select" label="Unit kerja pemilik" :options="units" required :error="form.errors.unit_id" />
      <Field v-model="form.owner_id" type="select" label="Risk owner" :options="owners" required :error="form.errors.owner_id" />
      <Field v-model="form.category_id" type="select" label="Kategori (taksonomi)" :options="categories" required :error="form.errors.category_id" :hint="cat ? `Appetite ${cat.appetite} · Tolerance ${cat.tolerance}` : ''" />
      <Field v-model="form.objective_id" type="select" label="Sasaran strategis terkait" :options="objectives.map((o) => ({ id: o.id, name: `${o.code} · ${o.name}` }))" :error="form.errors.objective_id" />
      <Field v-model="form.process_id" type="select" label="Proses bisnis" :options="processes" :error="form.errors.process_id" />
      <div class="row" style="gap:12px"><Field v-model="form.source_type" type="select" label="Sumber" :options="{ internal: 'Internal', external: 'Eksternal' }" empty="" required style="flex:1" /><Field v-model="form.source_kind" type="select" label="Jenis sumber" :options="L.source_kinds" empty="" required style="flex:1" :error="form.errors.source_kind" /></div>
      <Field v-model="form.cause" type="textarea" label="Penyebab (karena…)" required span :error="form.errors.cause" :rows="2" maxlength="2000" />
      <Field v-model="form.event" type="textarea" label="Peristiwa risiko (mungkin terjadi…)" required span :error="form.errors.event" :rows="2" maxlength="2000" />
      <Field v-model="form.impact" type="textarea" label="Dampak (yang berdampak pada…)" required span :error="form.errors.impact" :rows="2" maxlength="2000" />
      <div v-if="statement" class="alert-box info span" style="grid-column:1/-1"><Icon name="info" /><span><b>Pernyataan risiko:</b> {{ statement }}</span></div>
      <Field v-model="form.existing_controls" type="textarea" label="Kontrol yang sudah ada (narasi)" span :rows="2" maxlength="4000" />
      <div class="field span"><label>Kaitkan kontrol terdaftar</label><div class="chips-sel"><label v-for="c in controls" :key="c.id" :class="{ on: form.control_ids.includes(c.id) }"><input type="checkbox" :checked="form.control_ids.includes(c.id)" style="display:none" @change="toggleControl(c.id)"><span class="mono">{{ c.code }}</span> {{ c.name }}</label></div></div>
    </div></div>

    <div v-show="step === 2" class="s-grid">
      <div v-for="p in [['inherent', 'Inheren (sebelum kontrol)'], ['residual', 'Residual (setelah kontrol saat ini)'], ['target', 'Target (setelah treatment)']]" :key="p[0]" class="card" style="grid-column:span 4"><div class="card-h"><h3>{{ p[1] }}</h3></div><div class="card-b stack">
        <Heatmap pick :matrix="matrix" :selected="`${form[p[0] + '_l']}-${form[p[0] + '_i']}`" compact @select="(k, l, i) => pick(p[0], k, l, i)" />
        <div class="row between"><div><span class="hint">Kemungkinan × Dampak</span><div class="score-big">{{ score(form[p[0] + '_l'], form[p[0] + '_i']) }}</div></div><div class="stack" style="gap:4px;align-items:flex-end"><Pill kind="level" :value="lvOf(form[p[0] + '_l'], form[p[0] + '_i'])" /><span class="hint">L{{ form[p[0] + '_l'] }} · I{{ form[p[0] + '_i'] }}</span></div></div>
        <div class="row" style="gap:8px"><Field v-model="form[p[0] + '_l']" type="select" label="Kemungkinan" :options="(criteria?.likelihood || [1,2,3,4,5].map((v)=>({v,label:'Skala '+v}))).map((x) => ({ id: x.v, name: `${x.v} · ${x.label}` }))" empty="" style="flex:1" :error="form.errors[p[0] + '_l']" /><Field v-model="form[p[0] + '_i']" type="select" label="Dampak" :options="(criteria?.impact || [1,2,3,4,5].map((v)=>({v,label:'Skala '+v}))).map((x) => ({ id: x.v, name: `${x.v} · ${x.label}` }))" empty="" style="flex:1" /></div>
        <details v-if="p[0] !== 'target' && dims.length"><summary class="hint" style="cursor:pointer">Dampak per dimensi (opsional, nilai tertinggi dipakai)</summary><div class="form-grid" style="margin-top:8px"><Field v-for="d in dims" :key="d.key" v-model="form[p[0] + '_dims'][d.key]" type="select" :label="d.label" :options="[1, 2, 3, 4, 5].map((v) => ({ id: v, name: String(v) }))" empty="—" @update:modelValue="dimChange(p[0])" /></div></details>
      </div></div>
      <div v-if="warn.length" class="alert-box bad" style="grid-column:1/-1"><Icon name="alert" /><span>{{ warn.join(' ') }}</span></div>
    </div>

    <div v-show="step === 3" class="card"><div class="card-h"><h3>3. Evaluasi & treatment</h3><span class="sub">ISO 31000 §6.4.4 – §6.5</span></div><div class="card-b form-grid">
      <div class="alert-box" style="grid-column:1/-1" :class="{ ok: evalOf(score(form.residual_l, form.residual_i)) === 'acceptable', info: evalOf(score(form.residual_l, form.residual_i)) === 'monitor', warn: evalOf(score(form.residual_l, form.residual_i)) === 'treat', bad: ['escalate', 'critical'].includes(evalOf(score(form.residual_l, form.residual_i))) }"><Icon name="scale" /><span>Skor residual <b>{{ score(form.residual_l, form.residual_i) }}</b> {{ cat ? `dibandingkan appetite ${cat.appetite} dan tolerance ${cat.tolerance} kategori ${cat.name}` : '' }} → status evaluasi <b>{{ L.evaluations[evalOf(score(form.residual_l, form.residual_i))] }}</b>.{{ score(form.residual_l, form.residual_i) >= 16 ? ' Persetujuan wajib sampai Management.' : '' }}</span></div>
      <Field v-model="form.treatment" type="select" label="Opsi treatment" :options="L.treatments" empty="" required :error="form.errors.treatment" />
      <Field v-model="form.due_date" type="date" label="Target penyelesaian treatment" :error="form.errors.due_date" />
      <Field v-model="form.treatment_note" type="textarea" label="Rencana / catatan treatment" span :rows="3" maxlength="4000" hint="Action plan terperinci dapat ditambahkan setelah risiko disimpan." />
    </div></div>

    <div v-show="step === 4" class="card"><div class="card-h"><h3>4. Tinjau & simpan</h3></div><div class="card-b stack">
      <dl class="kv"><dt>Nama</dt><dd><b>{{ form.name || '—' }}</b></dd><dt>Pernyataan</dt><dd>{{ statement || '—' }}</dd><dt>Unit / pemilik</dt><dd>{{ units.find((u) => u.id == form.unit_id)?.name || '—' }} · {{ owners.find((o) => o.id == form.owner_id)?.name || '—' }}</dd><dt>Kategori</dt><dd>{{ cat?.name || '—' }}</dd>
        <dt>Skor</dt><dd>Inheren <b>{{ score(form.inherent_l, form.inherent_i) }}</b> → Residual <b>{{ score(form.residual_l, form.residual_i) }}</b> (<Pill kind="level" :value="lvOf(form.residual_l, form.residual_i)" />) → Target <b>{{ score(form.target_l, form.target_i) }}</b></dd>
        <dt>Treatment</dt><dd>{{ L.treatments[form.treatment] }}</dd></dl>
      <Field v-model="form.note" :label="edit ? 'Catatan perubahan (disimpan pada riwayat versi)' : 'Catatan versi awal'" maxlength="1000" />
      <p class="hint">{{ edit ? 'Perubahan skor pada risiko yang sudah disetujui akan otomatis membuat pengajuan perubahan skor.' : 'Risiko disimpan sebagai draft. Ajukan persetujuan dari halaman detail risiko.' }}</p>
    </div></div>

    <div class="row between" style="margin-top:14px"><button type="button" class="btn ghost c-indigo" :disabled="step === 1" @click="step--">‹ Sebelumnya</button>
      <div class="row"><button v-if="step < 4" type="button" class="btn c-blue" :disabled="!stepOk" @click="step++">Berikutnya ›</button><button v-if="step === 4 || edit" type="submit" class="btn c-green" :disabled="form.processing || warn.length"><Icon name="send" />{{ edit ? 'Simpan perubahan' : 'Simpan draft risiko' }}</button></div></div>
  </form>
</template>
